<?php

declare(strict_types=1);

namespace PbxApi\Drivers;

use PDO;
use PbxApi\Config\Config;
use PbxApi\Contracts\PbxDriverInterface;

/**
 * FreePBX driver.
 *
 * FreePBX (https://www.freepbx.org) is the most widely deployed Asterisk
 * GUI.  This driver extends the generic AsteriskDriver and adds:
 *
 *  - Bootstrap from /etc/freepbx.conf (PHP config file) or environment vars
 *  - BMO (Bootstrap Module Object) style extension management
 *  - Queue member management via ARI/AMI
 *  - Access to the FreePBX REST API (fpbx_rest) when available
 *
 * All telephony (AMI/ARI) and CDR methods are inherited from AsteriskDriver.
 */
class FreePBXDriver extends AsteriskDriver
{
    /** @var array<string, string> Parsed freepbx.conf values */
    private array $fpbxConf = [];

    public function __construct(?Config $config = null)
    {
        $cfg = $config ?? Config::getInstance();
        $this->loadFreePBXConf($cfg->get('FREEPBX_CONF_PATH', '/etc/freepbx.conf'));
        $this->injectFreePBXCredentials($cfg);
        parent::__construct($cfg);
    }

    // ----------------------------------------------------------------
    // FreePBX-specific extension management
    // ----------------------------------------------------------------

    /**
     * {@inheritdoc}
     *
     * FreePBX stores PJSIP endpoints in the `pjsip` table (keyword/data rows)
     * or the legacy `sip` table, depending on the configured channel driver.
     */
    public function getSipExtensions(): array
    {
        // Try PJSIP endpoints table first (FreePBX 14+)
        try {
            $this->dbConnect('asterisk');
            $stmt = $this->db->query(
                "SELECT id, keyword, data FROM pjsip ORDER BY id, flags"
            );
            if ($stmt !== false) {
                $rows    = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                $indexed = [];
                foreach ($rows as $row) {
                    $indexed[$row['id']][$row['keyword']] = $row['data'];
                }
                if (!empty($indexed)) {
                    return $indexed;
                }
            }
        } catch (\Exception $e) {
            // Fall through to parent implementation
        }

        return parent::getSipExtensions();
    }

    // ----------------------------------------------------------------
    // Queue management
    // ----------------------------------------------------------------

    /**
     * Return all FreePBX queue definitions from the database.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getQueues(): array
    {
        $this->dbConnect('asterisk');
        $stmt = $this->db->query("SELECT * FROM queues_config ORDER BY extension");
        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    /**
     * Return the list of static members for a given queue.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getQueueMembers(string $queue): array
    {
        $this->dbConnect('asterisk');
        $stmt = $this->db->prepare(
            "SELECT * FROM queue_details WHERE id = :q AND keyword = 'member'"
        );
        $stmt->execute([':q' => $queue]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Add a device to a queue at runtime via AMI.
     *
     * @param string  $queue    Queue name (e.g. "sales")
     * @param string  $device   Device string (e.g. "PJSIP/200" or "SIP/200")
     * @param int     $penalty  Agent penalty (0 = highest priority)
     */
    public function queueAddMember(string $queue, string $device, int $penalty = 0): bool
    {
        $this->amiConnect();
        try {
            $this->amiSend(
                "Action: QueueAdd\r\nQueue: {$queue}\r\nInterface: {$device}\r\nPenalty: {$penalty}\r\n\r\n"
            );
            return true;
        } finally {
            $this->amiDisconnect();
        }
    }

    /**
     * Remove a device from a queue at runtime via AMI.
     */
    public function queueRemoveMember(string $queue, string $device): bool
    {
        $this->amiConnect();
        try {
            $this->amiSend(
                "Action: QueueRemove\r\nQueue: {$queue}\r\nInterface: {$device}\r\n\r\n"
            );
            return true;
        } finally {
            $this->amiDisconnect();
        }
    }

    // ----------------------------------------------------------------
    // FreePBX module helpers
    // ----------------------------------------------------------------

    /**
     * Trigger a FreePBX "fwconsole reload" to regenerate Asterisk config.
     *
     * fwconsole is the modern replacement for module_admin in FreePBX 13+.
     */
    public function fwconsoleReload(): void
    {
        exec('/usr/sbin/fwconsole reload 2>/dev/null');
    }

    /**
     * Return the installed FreePBX framework version.
     */
    public function getFreePBXVersion(): string
    {
        $out = [];
        exec('/usr/sbin/fwconsole --version 2>/dev/null', $out);
        return $out[0] ?? 'unknown';
    }

    /**
     * Return the value of a FreePBX advanced setting stored in `admin` table.
     *
     * @param string $key  e.g. "ASTERISK_RESTART_CMD"
     */
    public function getAdvancedSetting(string $key): string
    {
        try {
            $this->dbConnect('asterisk');
            $stmt = $this->db->prepare(
                "SELECT value FROM admin WHERE variable = :k LIMIT 1"
            );
            $stmt->execute([':k' => $key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (string)$row['value'] : '';
        } catch (\Exception $e) {
            return '';
        }
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    /**
     * Parse /etc/freepbx.conf.
     *
     * The file is a PHP script that assigns values to $amp_conf[] — we
     * extract the key=value pairs with a simple regex rather than eval()-ing
     * the file for security reasons.
     */
    private function loadFreePBXConf(string $confPath): void
    {
        if (!is_readable($confPath)) {
            return;
        }
        $content = @file_get_contents($confPath);
        if ($content === false) {
            return;
        }

        // Match: $amp_conf['KEY'] = 'VALUE'; or $amp_conf["KEY"] = "VALUE";
        preg_match_all(
            '/\$amp_conf\s*\[\s*[\'"]([^\'"]+)[\'"]\s*\]\s*=\s*[\'"]([^\'"]*)[\'"]/',
            $content,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $m) {
            $this->fpbxConf[$m[1]] = $m[2];
        }
    }

    /**
     * Inject DB credentials from the parsed freepbx.conf into the environment
     * so that the parent AsteriskDriver picks them up via Config.
     */
    private function injectFreePBXCredentials(Config $cfg): void
    {
        if ($cfg->get('DB_PASSWORD') !== '') {
            return; // explicit env var wins
        }

        $map = [
            'AMPDBHOST' => 'DB_HOST',
            'AMPDBUSER' => 'DB_USERNAME',
            'AMPDBPASS' => 'DB_PASSWORD',
        ];

        foreach ($map as $fpbxKey => $envKey) {
            if (isset($this->fpbxConf[$fpbxKey])) {
                putenv("{$envKey}={$this->fpbxConf[$fpbxKey]}");
                $_ENV[$envKey] = $this->fpbxConf[$fpbxKey];
            }
        }

        if (!empty($this->fpbxConf)) {
            Config::reset();
        }
    }
}
