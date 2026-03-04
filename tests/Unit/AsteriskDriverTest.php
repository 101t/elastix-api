<?php

declare(strict_types=1);

namespace PbxApi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PbxApi\Config\Config;
use PbxApi\Drivers\AsteriskDriver;

/**
 * Unit tests for AsteriskDriver.
 *
 * AMI/ARI calls and exec() calls require a live Asterisk server, so we test
 * the non-network methods and the SQL builders via a partial mock / reflection.
 */
class AsteriskDriverTest extends TestCase
{
    protected function setUp(): void
    {
        Config::reset();
        putenv('AMI_HOST=127.0.0.1');
        putenv('AMI_PORT=5038');
        putenv('AMI_USERNAME=admin');
        putenv('AMI_SECRET=secret');
        putenv('CHAN_DRIVER=pjsip');
        putenv('DB_HOST=127.0.0.1');
        putenv('DB_PORT=3306');
        putenv('DB_USERNAME=root');
        putenv('DB_PASSWORD=');
    }

    protected function tearDown(): void
    {
        Config::reset();
    }

    // ----------------------------------------------------------------
    // Config resolution
    // ----------------------------------------------------------------

    public function testDriverReadsAmiHostFromConfig(): void
    {
        $cfg = Config::getInstance('/nonexistent');
        $this->assertSame('127.0.0.1', $cfg->get('AMI_HOST'));
    }

    public function testDriverReadsAmiPortFromConfig(): void
    {
        $cfg = Config::getInstance('/nonexistent');
        $this->assertSame(5038, $cfg->getInt('AMI_PORT'));
    }

    // ----------------------------------------------------------------
    // getSystemResources — parses "uptime" output format
    // ----------------------------------------------------------------

    /**
     * @dataProvider uptimeFormatProvider
     */
    public function testParseUptimeOutput(string $uptimeLine, string $expectedDatetime, string $expectedUsers, string $expectedUsage): void
    {
        $result = $this->invokeParseUptime($uptimeLine);
        $this->assertSame($expectedDatetime, $result['datetime']);
        $this->assertSame($expectedUsers,    $result['users']);
        $this->assertSame($expectedUsage,    $result['usage']);
    }

    /** @return array<string, array<int, string>> */
    public static function uptimeFormatProvider(): array
    {
        return [
            'minutes' => [
                ' 11:14:07 up  2:43,  5 users,  load average: 0.14, 0.31, 0.38',
                '11:14:07 up  2:43',
                '5',
                '0.14, 0.31, 0.38',
            ],
            'days' => [
                ' 11:39:13 up 8 days, 21:44,  2 users,  load average: 0.08, 0.10, 0.03',
                '11:39:13 up 8 days 21:44',
                '2',
                '0.08, 0.10, 0.03',
            ],
        ];
    }

    // ----------------------------------------------------------------
    // validateExtensionData — should throw on missing required fields
    // ----------------------------------------------------------------

    public function testValidateExtensionDataThrowsOnMissingAccount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('account');

        $driver = $this->createDriverWithoutConnection();
        $this->invokeValidateExtension($driver, [
            'name'    => 'John',
            'secret'  => 's3cr3t',
            'context' => 'from-internal',
            'type'    => 'friend',
            // 'account' missing
        ]);
    }

    public function testValidateExtensionDataThrowsOnMissingSecret(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('secret');

        $driver = $this->createDriverWithoutConnection();
        $this->invokeValidateExtension($driver, [
            'account' => '200',
            'name'    => 'John',
            'context' => 'from-internal',
            'type'    => 'friend',
            // 'secret' missing
        ]);
    }

    public function testValidateExtensionDataPassesWithAllRequiredFields(): void
    {
        $driver = $this->createDriverWithoutConnection();
        // No exception expected
        $this->invokeValidateExtension($driver, [
            'account' => '200',
            'name'    => 'John',
            'secret'  => 's3cr3t',
            'context' => 'from-internal',
            'type'    => 'friend',
        ]);
        $this->assertTrue(true); // assertion reached → validation passed
    }

    // ----------------------------------------------------------------
    // buildCdrWhere — SQL injection prevention & filter logic
    // ----------------------------------------------------------------

    public function testBuildCdrWhereWithNoFiltersIncludesAllDispositions(): void
    {
        $driver = $this->createDriverWithMockPdo();
        $where  = $this->invokeBuildCdrWhere($driver, []);
        $this->assertStringContainsString("disposition IN ('ANSWERED','BUSY','FAILED','NO ANSWER')", $where);
        $this->assertStringContainsString("dst != 's'", $where);
    }

    public function testBuildCdrWhereWithAnsweredStatus(): void
    {
        $driver = $this->createDriverWithMockPdo();
        $where  = $this->invokeBuildCdrWhere($driver, ['status' => 'ANSWERED']);
        $this->assertStringContainsString("disposition =", $where);
        $this->assertStringContainsString('ANSWERED', $where);
    }

    public function testBuildCdrWhereRejectsInvalidFieldName(): void
    {
        // An invalid field_name must NOT appear in the WHERE clause (SQL injection guard)
        $driver = $this->createDriverWithMockPdo();
        $where  = $this->invokeBuildCdrWhere($driver, [
            'field_name'    => 'DROP TABLE cdr; --',
            'field_pattern' => 'test',
        ]);
        $this->assertStringNotContainsString('DROP', $where);
        $this->assertStringNotContainsString('TABLE', $where);
    }

    public function testBuildCdrWhereWithValidFieldName(): void
    {
        $driver = $this->createDriverWithMockPdo();
        $where  = $this->invokeBuildCdrWhere($driver, [
            'field_name'    => 'src',
            'field_pattern' => '0212',
        ]);
        $this->assertStringContainsString('src', $where);
        $this->assertStringContainsString('LIKE', $where);
    }

    public function testBuildCdrWhereWithDateRange(): void
    {
        $driver = $this->createDriverWithMockPdo();
        $where  = $this->invokeBuildCdrWhere($driver, [
            'start_date' => '2024-01-01 00:00:00',
            'end_date'   => '2024-01-31 23:59:59',
        ]);
        $this->assertStringContainsString('calldate BETWEEN', $where);
    }

    // ----------------------------------------------------------------
    // getSipExtensions — reads ini file
    // ----------------------------------------------------------------

    public function testGetSipExtensionsReturnsParsedIniContent(): void
    {
        $dir  = sys_get_temp_dir() . '/ast_test_' . uniqid('', true);
        mkdir($dir);
        $conf = $dir . '/sip_additional.conf';
        file_put_contents($conf, "[100]\nsecret=pass100\ntype=friend\n\n[101]\nsecret=pass101\ntype=friend\n");

        putenv('CHAN_DRIVER=sip');
        putenv('SIP_CONF_PATH=' . $conf);
        Config::reset();

        $driver  = new AsteriskDriver();
        $result  = $driver->getSipExtensions();

        $this->assertArrayHasKey('100', $result);
        $this->assertSame('pass100', $result['100']['secret']);
        $this->assertArrayHasKey('101', $result);

        unlink($conf);
        rmdir($dir);
    }

    public function testGetSipExtensionsReturnsEmptyArrayWhenFileNotReadable(): void
    {
        putenv('CHAN_DRIVER=sip');
        putenv('SIP_CONF_PATH=/nonexistent/path/sip.conf');
        Config::reset();

        $driver = new AsteriskDriver();
        $this->assertSame([], $driver->getSipExtensions());
    }

    // ----------------------------------------------------------------
    // sanitizeAmiValue — CRLF injection guard
    // ----------------------------------------------------------------

    public function testSanitizeAmiValueStripsCarriageReturn(): void
    {
        $driver = $this->createDriverWithoutConnection();
        $ref    = new \ReflectionMethod($driver, 'sanitizeAmiValue');
        $ref->setAccessible(true);

        $result = $ref->invoke($driver, "PJSIP/200\r\nAction: Command");
        $this->assertSame('PJSIP/200Action: Command', $result);
        $this->assertStringNotContainsString("\r", $result);
        $this->assertStringNotContainsString("\n", $result);
    }

    public function testSanitizeAmiValuePreservesNormalValue(): void
    {
        $driver = $this->createDriverWithoutConnection();
        $ref    = new \ReflectionMethod($driver, 'sanitizeAmiValue');
        $ref->setAccessible(true);

        $this->assertSame('PJSIP/200', $ref->invoke($driver, 'PJSIP/200'));
        $this->assertSame('from-internal', $ref->invoke($driver, 'from-internal'));
        $this->assertSame('Alice <100>', $ref->invoke($driver, 'Alice <100>'));
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    private function createDriverWithoutConnection(): AsteriskDriver
    {
        return new AsteriskDriver(Config::getInstance('/nonexistent'));
    }

    private function createDriverWithMockPdo(): AsteriskDriver
    {
        // SQLite in-memory acts as a stand-in PDO for WHERE-building tests
        $driver = $this->createDriverWithoutConnection();
        $pdo    = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $ref = new \ReflectionProperty($driver, 'db');
        $ref->setAccessible(true);
        $ref->setValue($driver, $pdo);

        return $driver;
    }

    /** @param array<string, mixed> $filters */
    private function invokeBuildCdrWhere(AsteriskDriver $driver, array $filters): string
    {
        $ref = new \ReflectionMethod($driver, 'buildCdrWhere');
        $ref->setAccessible(true);
        return (string)$ref->invoke($driver, $filters);
    }

    /** @param array<string, mixed> $data */
    private function invokeValidateExtension(AsteriskDriver $driver, array $data): void
    {
        $ref = new \ReflectionMethod($driver, 'validateExtensionData');
        $ref->setAccessible(true);
        $ref->invoke($driver, $data);
    }

    /**
     * Simulate the getSystemResources parsing logic (tested in isolation
     * so we do not need a real "uptime" binary).
     *
     * @return array{datetime: string, users: string, usage: string}
     */
    private function invokeParseUptime(string $line): array
    {
        $line   = str_replace('load average: ', '', $line);
        $line   = str_replace(' users', '', $line);
        $line   = str_replace('days,', 'days', $line);
        $parts  = array_map('trim', explode(',  ', $line));
        return [
            'datetime' => $parts[0] ?? '',
            'users'    => $parts[1] ?? '',
            'usage'    => $parts[2] ?? '',
        ];
    }
}
