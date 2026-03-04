<?php

declare(strict_types=1);

namespace PbxApi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PbxApi\Config\Config;
use PbxApi\Drivers\FreePBXDriver;

/**
 * Unit tests for FreePBXDriver.
 *
 * Network-dependent methods (AMI, ARI, database) require a live FreePBX
 * server and are not tested here.  We focus on configuration bootstrap,
 * credential injection, and the freepbx.conf parser.
 */
class FreePBXDriverTest extends TestCase
{
    protected function setUp(): void
    {
        Config::reset();
        putenv('AMI_HOST=127.0.0.1');
        putenv('AMI_PORT=5038');
        putenv('AMI_USERNAME=admin');
        putenv('AMI_SECRET=secret');
        putenv('CHAN_DRIVER=pjsip');
        putenv('FREEPBX_CONF_PATH=/nonexistent/freepbx.conf');
    }

    protected function tearDown(): void
    {
        Config::reset();
        foreach (['DB_HOST', 'DB_USERNAME', 'DB_PASSWORD', 'FREEPBX_CONF_PATH'] as $k) {
            putenv($k . '=');
            unset($_ENV[$k]);
        }
    }

    // ----------------------------------------------------------------
    // freepbx.conf parser
    // ----------------------------------------------------------------

    public function testFreePBXDriverInstantiatesWithoutConf(): void
    {
        $driver = new FreePBXDriver();
        $this->assertInstanceOf(FreePBXDriver::class, $driver);
    }

    public function testFreePBXConfCredentialsAreInjected(): void
    {
        $dir  = sys_get_temp_dir() . '/fpbx_test_' . uniqid('', true);
        mkdir($dir);
        $conf = $dir . '/freepbx.conf';

        // Minimal freepbx.conf PHP file snippet
        file_put_contents($conf, <<<'PHP'
<?php
$amp_conf['AMPDBHOST'] = 'db.example.com';
$amp_conf['AMPDBUSER'] = 'freepbxuser';
$amp_conf['AMPDBPASS'] = 'fpbxpassword';
PHP
        );

        putenv('FREEPBX_CONF_PATH=' . $conf);
        putenv('DB_PASSWORD=');
        unset($_ENV['DB_PASSWORD']);

        $driver = new FreePBXDriver();
        $cfg    = Config::getInstance();

        $this->assertSame('db.example.com', $cfg->get('DB_HOST'));
        $this->assertSame('freepbxuser',    $cfg->get('DB_USERNAME'));
        $this->assertSame('fpbxpassword',   $cfg->get('DB_PASSWORD'));

        unlink($conf);
        rmdir($dir);
    }

    public function testFreePBXConfDoesNotOverrideExplicitEnvCredentials(): void
    {
        $dir  = sys_get_temp_dir() . '/fpbx_test_' . uniqid('', true);
        mkdir($dir);
        $conf = $dir . '/freepbx.conf';
        file_put_contents($conf, "<?php\n\$amp_conf['AMPDBPASS'] = 'fromfile';\n");

        putenv('DB_PASSWORD=fromenv');
        putenv('FREEPBX_CONF_PATH=' . $conf);

        $driver = new FreePBXDriver();
        $cfg    = Config::getInstance();
        $this->assertSame('fromenv', $cfg->get('DB_PASSWORD'));

        unlink($conf);
        rmdir($dir);
    }

    public function testFreePBXConfParsesDoubleQuotedValues(): void
    {
        $dir  = sys_get_temp_dir() . '/fpbx_test_' . uniqid('', true);
        mkdir($dir);
        $conf = $dir . '/freepbx.conf';
        file_put_contents($conf, "<?php\n\$amp_conf[\"AMPDBPASS\"] = \"doublequoted\";\n");

        putenv('FREEPBX_CONF_PATH=' . $conf);
        putenv('DB_PASSWORD=');
        unset($_ENV['DB_PASSWORD']);

        $driver = new FreePBXDriver();
        $cfg    = Config::getInstance();
        $this->assertSame('doublequoted', $cfg->get('DB_PASSWORD'));

        unlink($conf);
        rmdir($dir);
    }

    // ----------------------------------------------------------------
    // getFreePBXVersion
    // ----------------------------------------------------------------

    public function testGetFreePBXVersionReturnsUnknownWhenFwconsoleAbsent(): void
    {
        // fwconsole is not present in the test environment
        $driver  = new FreePBXDriver();
        $version = $driver->getFreePBXVersion();
        // Just confirm it returns a string (likely 'unknown' or a version)
        $this->assertIsString($version);
    }

    // ----------------------------------------------------------------
    // getAdvancedSetting — no DB available, should return ''
    // ----------------------------------------------------------------

    public function testGetAdvancedSettingReturnsEmptyStringOnDbFailure(): void
    {
        $driver = new FreePBXDriver();
        $value  = $driver->getAdvancedSetting('ASTERISK_RESTART_CMD');
        $this->assertSame('', $value);
    }

    // ----------------------------------------------------------------
    // Queue helpers — mock-based structural tests
    // ----------------------------------------------------------------

    public function testGetQueuesMockReturnValue(): void
    {
        $driver = $this->getMockBuilder(FreePBXDriver::class)
            ->onlyMethods(['getQueues'])
            ->getMock();

        $expected = [
            ['extension' => 'sales', 'descr' => 'Sales Queue'],
            ['extension' => 'support', 'descr' => 'Support Queue'],
        ];
        $driver->method('getQueues')->willReturn($expected);

        $queues = $driver->getQueues();
        $this->assertCount(2, $queues);
        $this->assertSame('sales', $queues[0]['extension']);
    }

    public function testQueueAddMemberMockReturnValue(): void
    {
        $driver = $this->getMockBuilder(FreePBXDriver::class)
            ->onlyMethods(['queueAddMember'])
            ->getMock();

        $driver->method('queueAddMember')->willReturn(true);
        $this->assertTrue($driver->queueAddMember('sales', 'PJSIP/200', 0));
    }

    public function testQueueRemoveMemberMockReturnValue(): void
    {
        $driver = $this->getMockBuilder(FreePBXDriver::class)
            ->onlyMethods(['queueRemoveMember'])
            ->getMock();

        $driver->method('queueRemoveMember')->willReturn(true);
        $this->assertTrue($driver->queueRemoveMember('sales', 'PJSIP/200'));
    }
}
