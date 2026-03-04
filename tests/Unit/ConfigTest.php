<?php

declare(strict_types=1);

namespace PbxApi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PbxApi\Config\Config;

class ConfigTest extends TestCase
{
    protected function setUp(): void
    {
        Config::reset();
        // Remove any lingering env overrides from previous tests
        foreach (['API_SECRET_KEY', 'PBX_DRIVER', 'AMI_HOST', 'AMI_PORT', 'ARI_ENABLED'] as $k) {
            putenv($k . '=');
            unset($_ENV[$k]);
        }
    }

    protected function tearDown(): void
    {
        Config::reset();
    }

    // ----------------------------------------------------------------

    public function testDefaultValues(): void
    {
        $cfg = Config::getInstance('/nonexistent');
        $this->assertSame('127.0.0.1', $cfg->get('AMI_HOST'));
        $this->assertSame(5038, $cfg->getInt('AMI_PORT'));
        $this->assertSame('asterisk', $cfg->get('PBX_DRIVER'));
        $this->assertFalse($cfg->getBool('ARI_ENABLED'));
    }

    public function testGetReturnsDefaultWhenKeyMissing(): void
    {
        $cfg = Config::getInstance('/nonexistent');
        $this->assertSame('fallback', $cfg->get('NONEXISTENT_KEY', 'fallback'));
    }

    public function testGetIntDefaultOnMissingKey(): void
    {
        $cfg = Config::getInstance('/nonexistent');
        $this->assertSame(42, $cfg->getInt('NONEXISTENT_KEY', 42));
    }

    public function testGetBoolTrueVariants(): void
    {
        foreach (['true', '1', 'yes', 'on', 'TRUE', 'YES'] as $value) {
            Config::reset();
            putenv('ARI_ENABLED=' . $value);
            $cfg = Config::getInstance('/nonexistent');
            $this->assertTrue($cfg->getBool('ARI_ENABLED'), "Expected true for value '{$value}'");
        }
    }

    public function testGetBoolFalseVariants(): void
    {
        Config::reset();
        putenv('ARI_ENABLED=false');
        $cfg = Config::getInstance('/nonexistent');
        $this->assertFalse($cfg->getBool('ARI_ENABLED'));
    }

    public function testEnvironmentVariableOverridesDefault(): void
    {
        putenv('AMI_HOST=10.0.0.1');
        $cfg = Config::getInstance('/nonexistent');
        $this->assertSame('10.0.0.1', $cfg->get('AMI_HOST'));
    }

    public function testDotEnvFileIsLoaded(): void
    {
        $dir = sys_get_temp_dir() . '/pbxapi_test_' . uniqid('', true);
        mkdir($dir);
        file_put_contents($dir . '/.env', "AMI_HOST=192.168.1.50\nPBX_DRIVER=freepbx\n");

        $cfg = Config::getInstance($dir);
        $this->assertSame('192.168.1.50', $cfg->get('AMI_HOST'));
        $this->assertSame('freepbx', $cfg->get('PBX_DRIVER'));

        unlink($dir . '/.env');
        rmdir($dir);
    }

    public function testDotEnvStripsQuotes(): void
    {
        $dir = sys_get_temp_dir() . '/pbxapi_test_' . uniqid('', true);
        mkdir($dir);
        file_put_contents($dir . '/.env', "API_SECRET_KEY=\"mySecret123\"\n");

        $cfg = Config::getInstance($dir);
        $this->assertSame('mySecret123', $cfg->get('API_SECRET_KEY'));

        unlink($dir . '/.env');
        rmdir($dir);
    }

    public function testDotEnvIgnoresComments(): void
    {
        $dir = sys_get_temp_dir() . '/pbxapi_test_' . uniqid('', true);
        mkdir($dir);
        file_put_contents($dir . '/.env', "# This is a comment\nAMI_PORT=9999\n");

        $cfg = Config::getInstance($dir);
        $this->assertSame(9999, $cfg->getInt('AMI_PORT'));

        unlink($dir . '/.env');
        rmdir($dir);
    }

    public function testEnvironmentVariableWinsOverDotEnv(): void
    {
        $dir = sys_get_temp_dir() . '/pbxapi_test_' . uniqid('', true);
        mkdir($dir);
        file_put_contents($dir . '/.env', "AMI_HOST=from_dotenv\n");

        putenv('AMI_HOST=from_env');
        $cfg = Config::getInstance($dir);
        $this->assertSame('from_env', $cfg->get('AMI_HOST'));

        unlink($dir . '/.env');
        rmdir($dir);
    }

    public function testSingletonReturnsSameInstance(): void
    {
        $a = Config::getInstance('/nonexistent');
        $b = Config::getInstance('/another_path');
        $this->assertSame($a, $b);
    }

    public function testResetAllowsNewInstance(): void
    {
        $a = Config::getInstance('/nonexistent');
        Config::reset();
        $b = Config::getInstance('/nonexistent');
        $this->assertNotSame($a, $b);
    }
}
