<?php

declare(strict_types=1);

namespace PbxApi\Config;

/**
 * Lightweight configuration loader.
 *
 * Resolution order for each key (highest wins):
 *   1. Real environment variables (putenv / $_ENV / $_SERVER)
 *   2. A .env file in $basePath
 *   3. Hard-coded defaults
 */
class Config
{
    /** @var array<string, string> */
    private array $data = [];

    /** @var self|null */
    private static ?self $instance = null;

    private function __construct(string $basePath)
    {
        $this->loadDefaults();
        $this->loadDotEnv($basePath);
        $this->loadEnvironment();
    }

    /**
     * Return the singleton instance, initialising it for $basePath on the
     * first call.  Subsequent calls with a different path are ignored so that
     * the instance is always consistent within a single request lifecycle.
     */
    public static function getInstance(string $basePath = ''): self
    {
        if (self::$instance === null) {
            self::$instance = new self($basePath ?: dirname(__DIR__, 2));
        }
        return self::$instance;
    }

    /** Reset the singleton (useful in tests). */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /** Retrieve a value; returns $default when the key is absent. */
    public function get(string $key, string $default = ''): string
    {
        return $this->data[$key] ?? $default;
    }

    /** Return a boolean-like value ('true'/'1'/'yes' → true). */
    public function getBool(string $key, bool $default = false): bool
    {
        if (!array_key_exists($key, $this->data)) {
            return $default;
        }
        return in_array(strtolower($this->data[$key]), ['true', '1', 'yes', 'on'], true);
    }

    /** Return an integer value. */
    public function getInt(string $key, int $default = 0): int
    {
        if (!array_key_exists($key, $this->data)) {
            return $default;
        }
        return (int) $this->data[$key];
    }

    // ----------------------------------------------------------------
    // Internal helpers
    // ----------------------------------------------------------------

    private function loadDefaults(): void
    {
        $this->data = [
            'API_SECRET_KEY'    => 'YOUR_SECRETKEY_50_RANDOM_CHARS',
            'PBX_DRIVER'        => 'asterisk',
            'AMI_HOST'          => '127.0.0.1',
            'AMI_PORT'          => '5038',
            'AMI_USERNAME'      => '',
            'AMI_SECRET'        => '',
            'AMI_CONF_PATH'     => '/etc/asterisk/manager.conf',
            'ARI_ENABLED'       => 'false',
            'ARI_HOST'          => '127.0.0.1',
            'ARI_PORT'          => '8088',
            'ARI_USERNAME'      => 'ari_user',
            'ARI_SECRET'        => 'ari_password',
            'ARI_SCHEME'        => 'http',
            'CHAN_DRIVER'        => 'pjsip',
            'SIP_CONF_PATH'     => '/etc/asterisk/sip_additional.conf',
            'PJSIP_CONF_PATH'   => '/etc/asterisk/pjsip.conf',
            'DB_HOST'           => 'localhost',
            'DB_PORT'           => '3306',
            'DB_USERNAME'       => 'root',
            'DB_PASSWORD'       => '',
            'DB_CHARSET'        => 'utf8mb4',
            'ISSABEL_CONF_PATH' => '/etc/issabel.conf',
            'FREEPBX_CONF_PATH' => '/etc/freepbx.conf',
            'RECORDINGS_PATH'   => '/var/spool/asterisk/monitor',
            'LOG_LEVEL'         => 'error',
            'LOG_PATH'          => '/var/log/pbx-api.log',
            'CORS_ALLOWED_ORIGINS' => '*',
        ];
    }

    /**
     * Parse a .env file located at $basePath/.env
     * Supports KEY=VALUE lines; lines starting with # are comments.
     */
    private function loadDotEnv(string $basePath): void
    {
        $file = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . '.env';
        if (!is_readable($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $eqPos = strpos($line, '=');
            if ($eqPos === false) {
                continue;
            }
            $key   = trim(substr($line, 0, $eqPos));
            $value = trim(substr($line, $eqPos + 1));

            // Strip optional surrounding quotes
            if (
                strlen($value) >= 2
                && (
                    ($value[0] === '"' && $value[-1] === '"')
                    || ($value[0] === "'" && $value[-1] === "'")
                )
            ) {
                $value = substr($value, 1, -1);
            }

            if ($key !== '') {
                $this->data[$key] = $value;
            }
        }
    }

    /**
     * Real environment variables (getenv / $_ENV) win over .env file and defaults.
     * Empty-string values are treated as "not set" so they do not erase defaults.
     */
    private function loadEnvironment(): void
    {
        foreach (array_keys($this->data) as $key) {
            $envVal = getenv($key);
            if ($envVal !== false && $envVal !== '') {
                $this->data[$key] = $envVal;
            } elseif (isset($_ENV[$key]) && $_ENV[$key] !== '') {
                $this->data[$key] = (string) $_ENV[$key];
            }
        }
    }
}
