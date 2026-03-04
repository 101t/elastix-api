<?php

declare(strict_types=1);

namespace PbxApi\Drivers;

use PDO;
use PbxApi\Config\Config;
use PbxApi\Contracts\PbxDriverInterface;

/**
 * Issabel PBX driver.
 *
 * Issabel (https://www.issabel.org) is the community-maintained successor to
 * Elastix, distributed as an AlmaLinux/CentOS-based ISO bundling Asterisk,
 * FreePBX and a custom web panel.
 *
 * Key differences from the legacy Elastix driver:
 *  - Configuration is read from /etc/issabel.conf (same key–value format)
 *  - Asterisk version is typically 18+ with chan_pjsip as the default driver
 *  - The MySQL schema for FreePBX modules is identical to stock FreePBX
 *
 * This driver extends AsteriskDriver so that all telephony (AMI/ARI) methods
 * are inherited; only the credential bootstrap and PBX-specific helpers are
 * overridden.
 */
class IssabelDriver extends AsteriskDriver
{
    public function __construct(?Config $config = null)
    {
        $cfg = $config ?? Config::getInstance();

        // Issabel stores MySQL root password in /etc/issabel.conf
        $confPath = $cfg->get('ISSABEL_CONF_PATH', '/etc/issabel.conf');
        $this->injectMysqlCredentials($cfg, $confPath);

        // injectMysqlCredentials() may call Config::reset(), so re-fetch the
        // singleton to ensure the parent AsteriskDriver sees the injected credentials.
        $cfg = Config::getInstance();
        parent::__construct($cfg);
    }

    // ----------------------------------------------------------------
    // System information — Issabel-specific paths
    // ----------------------------------------------------------------

    /**
     * {@inheritdoc}
     *
     * Adds Issabel-specific directories (e.g. /opt/issabel) to the usage map.
     */
    public function getDiskUsage(): array
    {
        $usage = parent::getDiskUsage();

        // Issabel stores add-on modules under /opt/issabel
        $out = [];
        exec('du -sh /opt/issabel 2>/dev/null', $out);
        $usage['issabel_modules'] = isset($out[0]) ? explode("\t", $out[0]) : [];

        return $usage;
    }

    // ----------------------------------------------------------------
    // Issabel Admin API helpers
    // ----------------------------------------------------------------

    /**
     * Return Issabel server version string from /etc/issabel-release (if present).
     */
    public function getIssabelVersion(): string
    {
        $releaseFile = '/etc/issabel-release';
        if (!is_readable($releaseFile)) {
            return 'unknown';
        }
        $content = @file_get_contents($releaseFile);
        if ($content === false) {
            return 'unknown';
        }
        // File typically contains a single version line, e.g. "4.0.0"
        return trim(explode("\n", $content)[0]);
    }

    /**
     * Return the Fail2Ban jail status for Issabel's built-in firewall.
     *
     * @return array{jails: string[], status: string}
     */
    public function getFirewallStatus(): array
    {
        $out = [];
        exec('sudo /usr/bin/fail2ban-client status 2>&1', $out);
        $output = implode("\n", $out);

        $jails = [];
        if (preg_match('/Jail list:\s+(.+)/i', $output, $m)) {
            $jails = array_map('trim', explode(',', $m[1]));
        }

        return [
            'jails'  => $jails,
            'status' => $output,
        ];
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    /**
     * Parse the Issabel config file and inject DB credentials into the
     * Config singleton so that the parent AsteriskDriver uses them.
     */
    private function injectMysqlCredentials(Config $config, string $confPath): void
    {
        // Only override when the caller has not already set DB_PASSWORD explicitly
        if ($config->get('DB_PASSWORD') !== '' || !is_readable($confPath)) {
            return;
        }

        $lines = @file($confPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }

        $data = [];
        foreach ($lines as $line) {
            if (strpos($line, '=') === false) {
                continue;
            }
            [$key, $val] = array_map('trim', explode('=', $line, 2));
            $data[$key] = $val;
        }

        if (isset($data['mysqlrootpwd'])) {
            putenv('DB_PASSWORD=' . $data['mysqlrootpwd']);
            $_ENV['DB_PASSWORD'] = $data['mysqlrootpwd'];
            // Re-initialise Config so the new env var is picked up
            Config::reset();
        }
    }
}
