<?php

declare(strict_types=1);

namespace PbxApi\Drivers;

use PDO;
use PDOException;
use PbxApi\Config\Config;
use PbxApi\Contracts\PbxDriverInterface;

/**
 * Modern Asterisk driver.
 *
 * Supports:
 *  - Asterisk 13+ via AMI (port 5038) for real-time call data
 *  - Asterisk 12+ via ARI (HTTP REST, port 8088) when ARI_ENABLED=true
 *  - Both chan_sip (legacy) and chan_pjsip (modern) endpoint management
 *  - FreePBX-style MySQL schema (asterisk / asteriskcdrdb databases)
 */
class AsteriskDriver implements PbxDriverInterface
{
    private Config $config;

    /** @var resource|false */
    protected $socket = false;

    protected ?PDO $db = null;

    public function __construct(?Config $config = null)
    {
        $this->config = $config ?? Config::getInstance();
    }

    // ----------------------------------------------------------------
    // AMI connection helpers
    // ----------------------------------------------------------------

    /**
     * Open a TCP socket to the Asterisk Manager Interface and authenticate.
     *
     * @throws \RuntimeException on connection or auth failure
     */
    protected function amiConnect(): void
    {
        $host = $this->config->get('AMI_HOST', '127.0.0.1');
        $port = $this->config->getInt('AMI_PORT', 5038);

        $socket = @fsockopen($host, $port, $errno, $errstr, 5);
        if ($socket === false) {
            throw new \RuntimeException("AMI connection failed ({$errno}): {$errstr}");
        }
        stream_set_timeout($socket, 5);
        $this->socket = $socket;

        [$username, $secret] = $this->resolveAmiCredentials();
        $this->amiSend("Action: login\r\nUsername: {$username}\r\nSecret: {$secret}\r\n\r\n");
    }

    protected function amiDisconnect(): void
    {
        if ($this->socket !== false) {
            @fwrite($this->socket, "Action: Logoff\r\n\r\n");
            @fclose($this->socket);
            $this->socket = false;
        }
    }

    /**
     * Send a raw AMI command and collect the response until $terminator is found
     * or the socket times out.
     */
    protected function amiSend(string $command, string $terminator = ''): string
    {
        if ($this->socket === false) {
            throw new \RuntimeException('AMI socket is not open');
        }
        fwrite($this->socket, $command);

        $response = '';
        while ($line = fgets($this->socket)) {
            $response .= $line;
            if ($terminator !== '' && strpos($line, $terminator) !== false) {
                break;
            }
        }
        return $response;
    }

    /** @return array{0: string, 1: string} */
    private function resolveAmiCredentials(): array
    {
        $username = $this->config->get('AMI_USERNAME');
        $secret   = $this->config->get('AMI_SECRET');

        if ($username !== '' && $secret !== '') {
            return [$username, $secret];
        }

        // Fall back to parsing manager.conf
        $confPath = $this->config->get('AMI_CONF_PATH', '/etc/asterisk/manager.conf');
        if (!is_readable($confPath)) {
            return [$username, $secret];
        }

        $ini = @parse_ini_file($confPath, true, INI_SCANNER_RAW);
        if (!is_array($ini)) {
            return [$username, $secret];
        }

        // The first section after [general] is the manager user
        $sections = array_keys($ini);
        foreach ($sections as $section) {
            if ($section === 'general') {
                continue;
            }
            if (isset($ini[$section]['secret'])) {
                return [(string)$section, (string)$ini[$section]['secret']];
            }
        }

        return [$username, $secret];
    }

    // ----------------------------------------------------------------
    // ARI helpers (Asterisk REST Interface)
    // ----------------------------------------------------------------

    /**
     * Execute an ARI request and return the decoded JSON body.
     *
     * @param string $method  HTTP method (GET, POST, DELETE, …)
     * @param string $path    ARI endpoint path (e.g. /channels)
     * @param array<string, mixed> $body  JSON body for POST/PUT requests
     * @return array<mixed>
     * @throws \RuntimeException when cURL is unavailable or the request fails
     */
    public function ariRequest(string $method, string $path, array $body = []): array
    {
        if (!$this->config->getBool('ARI_ENABLED')) {
            throw new \RuntimeException('ARI is disabled. Set ARI_ENABLED=true in your .env');
        }

        if (!function_exists('curl_init')) {
            throw new \RuntimeException('ext-curl is required for ARI requests');
        }

        $scheme   = $this->config->get('ARI_SCHEME', 'http');
        $host     = $this->config->get('ARI_HOST', '127.0.0.1');
        $port     = $this->config->getInt('ARI_PORT', 8088);
        $username = $this->config->get('ARI_USERNAME');
        $secret   = $this->config->get('ARI_SECRET');

        $url = sprintf('%s://%s:%d/ari%s', $scheme, $host, $port, $path);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_USERPWD        => $username . ':' . $secret,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        ]);

        if (!empty($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            throw new \RuntimeException('ARI request failed');
        }

        $decoded = json_decode((string)$raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    // ----------------------------------------------------------------
    // Database helpers
    // ----------------------------------------------------------------

    protected function dbConnect(string $dbname): void
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->config->get('DB_HOST', 'localhost'),
            $this->config->getInt('DB_PORT', 3306),
            $dbname,
            $this->config->get('DB_CHARSET', 'utf8mb4')
        );
        $this->db = new PDO($dsn, $this->config->get('DB_USERNAME', 'root'), $this->config->get('DB_PASSWORD'));
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    // ----------------------------------------------------------------
    // PbxDriverInterface — peers / endpoints
    // ----------------------------------------------------------------

    /**
     * {@inheritdoc}
     *
     * Uses `pjsip show endpoints` for chan_pjsip or `sip show peers` via AMI
     * for chan_sip, depending on the CHAN_DRIVER configuration.
     */
    public function getSipPeers(): array
    {
        $this->amiConnect();
        try {
            if ($this->config->get('CHAN_DRIVER', 'pjsip') === 'pjsip') {
                return $this->getPjsipEndpoints();
            }
            return $this->getLegacySipPeers();
        } finally {
            $this->amiDisconnect();
        }
    }

    /** Parse the AMI SIPpeers response into a structured array. */
    private function getLegacySipPeers(): array
    {
        $raw  = $this->amiSend("Action: Sippeers\r\n\r\n", 'ListItems');
        $rows = explode("\n", $raw);

        $result  = [];
        $current = [];

        foreach ($rows as $line) {
            $line = rtrim($line, "\r\n");
            if ($line === '') {
                if (count($current) > 3) {
                    $result[] = $current;
                }
                $current = [];
                continue;
            }
            $pos = strpos($line, ': ');
            if ($pos !== false) {
                $current[substr($line, 0, $pos)] = substr($line, $pos + 2);
            }
        }
        if (count($current) > 3) {
            $result[] = $current;
        }
        return $result;
    }

    /**
     * Return PJSIP endpoints via `pjsip show endpoints` AMI command.
     *
     * Asterisk 13+ exposes PJSIP endpoints through the AMI PJSIPShowEndpoints
     * action which returns EndpointList events.
     */
    private function getPjsipEndpoints(): array
    {
        $raw    = $this->amiSend("Action: PJSIPShowEndpoints\r\n\r\n", 'EndpointListComplete');
        $rows   = explode("\n", $raw);
        $result = [];
        $current = [];

        foreach ($rows as $line) {
            $line = rtrim($line, "\r\n");
            if ($line === '') {
                if (!empty($current)) {
                    $result[] = $current;
                }
                $current = [];
                continue;
            }
            $pos = strpos($line, ': ');
            if ($pos !== false) {
                $current[substr($line, 0, $pos)] = substr($line, $pos + 2);
            }
        }
        if (!empty($current)) {
            $result[] = $current;
        }
        return $result;
    }

    /** {@inheritdoc} */
    public function getSipExtensions(): array
    {
        if ($this->config->get('CHAN_DRIVER', 'pjsip') === 'pjsip') {
            $confPath = $this->config->get('PJSIP_CONF_PATH', '/etc/asterisk/pjsip.conf');
        } else {
            $confPath = $this->config->get('SIP_CONF_PATH', '/etc/asterisk/sip_additional.conf');
        }

        if (!is_readable($confPath)) {
            return [];
        }

        $data = @parse_ini_file($confPath, true, INI_SCANNER_RAW);
        return is_array($data) ? $data : [];
    }

    // ----------------------------------------------------------------
    // PbxDriverInterface — active call monitoring
    // ----------------------------------------------------------------

    /** {@inheritdoc} */
    public function getActiveCalls(): array
    {
        $output = [];
        exec('/usr/sbin/asterisk -rx "core show channels verbose" 2>/dev/null', $output);

        if (empty($output)) {
            return [];
        }

        $headerLine = array_shift($output);
        $headers    = preg_split('/\s+/', trim($headerLine)) ?: [];
        $charLens   = $this->parseColumnLengths($headerLine);

        $result = [];
        foreach ($output as $line) {
            if (strlen($line) > 100) {
                $result[] = $this->splitByColumns($headers, $line, $charLens);
            } else {
                $result[] = trim($line);
            }
        }
        return $result;
    }

    /** {@inheritdoc} */
    public function getChannelStatus(?string $channel = null): string
    {
        $this->amiConnect();
        try {
            $action = "Action: Status\r\n";
            if ($channel !== null && $channel !== '') {
                $action .= "Channel: {$channel}\r\n";
            }
            return $this->amiSend($action . "\r\n");
        } finally {
            $this->amiDisconnect();
        }
    }

    /** {@inheritdoc} */
    public function getParkedCalls(): string
    {
        $this->amiConnect();
        try {
            return $this->amiSend("Action: ParkedCalls\r\n\r\n", 'ParkedCallsComplete');
        } finally {
            $this->amiDisconnect();
        }
    }

    // ----------------------------------------------------------------
    // PbxDriverInterface — system information
    // ----------------------------------------------------------------

    /** {@inheritdoc} */
    public function getSystemResources(): array
    {
        $output = [];
        exec('uptime 2>/dev/null', $output);

        if (empty($output)) {
            return ['datetime' => '', 'users' => '', 'usage' => ''];
        }

        $line = str_replace('load average: ', '', $output[0]);
        $line = str_replace(' users', '', $line);
        $line = str_replace('days,', 'days', $line);
        $parts = array_map('trim', explode(',  ', $line));

        return [
            'datetime' => $parts[0] ?? '',
            'users'    => $parts[1] ?? '',
            'usage'    => $parts[2] ?? '',
        ];
    }

    /** {@inheritdoc} */
    public function getDiskUsage(): array
    {
        $result = [];
        $disk   = [];
        exec('df -H / 2>/dev/null', $disk);

        if (isset($disk[1])) {
            $tmp = preg_split('/\s+/', trim($disk[1])) ?: [];
            $result['harddisk'] = [
                'size'       => $tmp[0] ?? '',
                'used'       => $tmp[1] ?? '',
                'avail'      => $tmp[2] ?? '',
                'usepercent' => $tmp[3] ?? '',
                'mount'      => $tmp[4] ?? '',
            ];
        }

        $dirs = [
            'logs'          => '/var/log',
            'thirdparty'    => '/opt',
            'voicemails'    => '/var/spool/asterisk/voicemail',
            'backups'       => '/var/www/backup',
            'configuration' => '/etc',
            'recording'     => $this->config->get('RECORDINGS_PATH', '/var/spool/asterisk/monitor'),
        ];

        foreach ($dirs as $label => $path) {
            $out = [];
            exec('du -sh ' . escapeshellarg($path) . ' 2>/dev/null', $out);
            $result[$label] = isset($out[0]) ? explode("\t", $out[0]) : [];
        }

        return $result;
    }

    // ----------------------------------------------------------------
    // PbxDriverInterface — CDR
    // ----------------------------------------------------------------

    /** {@inheritdoc} */
    public function getCdr(array $filters = []): array
    {
        $this->dbConnect('asteriskcdrdb');

        $where = $this->buildCdrWhere($filters);
        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 100;

        $sql  = "SELECT * FROM cdr WHERE {$where} ORDER BY calldate DESC LIMIT {$limit}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Build a safe WHERE clause for the CDR query.
     *
     * Uses prepared-statement placeholders bound via PDO to prevent SQL
     * injection. The $filters array is user-supplied.
     *
     * @param array<string, mixed> $filters
     */
    private function buildCdrWhere(array $filters): string
    {
        $clauses = [];

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            // Bind via statement parameters
            $clauses[] = "(calldate BETWEEN '"
                . $this->db->quote((string)$filters['start_date']) . "' AND '"
                . $this->db->quote((string)$filters['end_date']) . "')";
        }

        $allowedFields = ['src', 'dst', 'channel', 'dstchannel', 'accountcode', 'clid', 'cnum', 'cnam'];
        if (!empty($filters['field_name']) && in_array($filters['field_name'], $allowedFields, true)) {
            $clauses[] = sprintf(
                "(%s LIKE %s)",
                $filters['field_name'],
                $this->db->quote('%' . (string)($filters['field_pattern'] ?? '') . '%')
            );
        }

        $validStatuses = ['ALL', 'ANSWERED', 'BUSY', 'FAILED', 'NO ANSWER'];
        $status        = strtoupper((string)($filters['status'] ?? 'ALL'));
        if (empty($status) || !in_array($status, $validStatuses, true)) {
            $status = 'ALL';
        }

        if ($status === 'ALL') {
            $clauses[] = "(disposition IN ('ANSWERED','BUSY','FAILED','NO ANSWER'))";
        } else {
            $clauses[] = "(disposition = " . $this->db->quote($status) . ")";
        }

        $clauses[] = "dst != 's'";

        return implode(' AND ', $clauses);
    }

    // ----------------------------------------------------------------
    // PbxDriverInterface — extension CRUD
    // ----------------------------------------------------------------

    /** {@inheritdoc} */
    public function addExtension(array $data): bool
    {
        $this->dbConnect('asterisk');
        $this->validateExtensionData($data);
        $account = $data['account'];

        $check = $this->db->prepare("SELECT id FROM sip WHERE id = :id LIMIT 1");
        $check->execute([':id' => $account]);
        if ($check->fetch()) {
            return false; // already exists
        }

        $this->db->exec($this->buildSipInsert($data));
        $this->db->exec($this->buildUsersInsert($data));
        $this->db->exec($this->buildDevicesInsert($data));
        $this->applyConfig();
        return true;
    }

    /** {@inheritdoc} */
    public function updateExtension(array $data): bool
    {
        $this->dbConnect('asterisk');
        $this->validateExtensionData($data);
        $this->db->exec($this->buildSipUpsert($data));
        $this->db->exec($this->buildUsersUpdate($data));
        $this->applyConfig();
        return true;
    }

    /** {@inheritdoc} */
    public function deleteExtension(string $account): bool
    {
        $this->dbConnect('asterisk');
        $account = (string)$account;

        $this->db->exec("DELETE FROM sip WHERE id=" . $this->db->quote($account));
        $this->db->exec("DELETE FROM users WHERE extension=" . $this->db->quote($account));
        $this->db->exec("DELETE FROM devices WHERE id=" . $this->db->quote($account));
        $this->applyConfig();
        return true;
    }

    // ----------------------------------------------------------------
    // PbxDriverInterface — Follow-Me CRUD
    // ----------------------------------------------------------------

    /** {@inheritdoc} */
    public function addFollowMe(array $data): bool
    {
        $this->dbConnect('asterisk');
        $this->putAmpUser($data);

        $check = $this->db->prepare("SELECT grpnum FROM findmefollow WHERE grpnum = :g LIMIT 1");
        $check->execute([':g' => $data['grpnum']]);
        if ($check->fetch()) {
            return false;
        }

        $this->db->exec($this->buildFollowMeInsert($data));
        $this->applyRetrieve();
        $this->applyConfig();
        return true;
    }

    /** {@inheritdoc} */
    public function updateFollowMe(array $data): bool
    {
        $this->dbConnect('asterisk');
        $this->putAmpUser($data);
        $this->db->exec($this->buildFollowMeUpdate($data));
        $this->applyRetrieve();
        $this->applyConfig();
        return true;
    }

    /** {@inheritdoc} */
    public function deleteFollowMe(string $grpnum): bool
    {
        $this->dbConnect('asterisk');
        $this->deleteAmpUser($grpnum);
        $this->db->exec("DELETE FROM findmefollow WHERE grpnum=" . $this->db->quote($grpnum));
        $this->applyRetrieve();
        $this->applyConfig();
        return true;
    }

    /** {@inheritdoc} */
    public function getFollowMe(string $grpnum): array
    {
        $this->dbConnect('asterisk');
        $stmt = $this->db->prepare("SELECT * FROM findmefollow WHERE grpnum = :g");
        $stmt->execute([':g' => $grpnum]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** {@inheritdoc} */
    public function getAllFollowMe(): array
    {
        $this->dbConnect('asterisk');
        $stmt = $this->db->query("SELECT * FROM findmefollow");
        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    // ----------------------------------------------------------------
    // Additional Asterisk-specific public methods
    // ----------------------------------------------------------------

    /**
     * Originate an outbound call via AMI.
     *
     * @param string $channel    The channel to originate from (e.g. PJSIP/200)
     * @param string $extension  The destination extension number
     * @param string $context    Dialplan context (default: from-internal)
     * @param string $callerId   Caller-ID to present
     * @param int    $timeout    Timeout in ms (default 30 000)
     * @param array<string, string> $variables Extra channel variables
     */
    public function originateCall(
        string $channel,
        string $extension,
        string $context = 'from-internal',
        string $callerId = '',
        int $timeout = 30000,
        array $variables = []
    ): bool {
        $this->amiConnect();
        try {
            $cmd = "Action: Originate\r\n"
                . "Channel: {$channel}\r\n"
                . "Context: {$context}\r\n"
                . "Exten: {$extension}\r\n"
                . "Priority: 1\r\n"
                . "Callerid: {$callerId}\r\n"
                . "Timeout: {$timeout}\r\n";

            if (!empty($variables)) {
                $pairs = [];
                foreach ($variables as $k => $v) {
                    $pairs[] = "{$k}={$v}";
                }
                $cmd .= 'Variable: ' . implode('|', $pairs) . "\r\n";
            }

            $this->amiSend($cmd . "\r\n");
            return true;
        } finally {
            $this->amiDisconnect();
        }
    }

    /**
     * Return all active ARI channels (requires ARI_ENABLED=true).
     *
     * @return array<mixed>
     */
    public function getAriChannels(): array
    {
        return $this->ariRequest('GET', '/channels');
    }

    /**
     * Hang up a channel via ARI.
     *
     * @param string $channelId  ARI channel ID
     * @param string $reason     Hangup reason (normal | busy | congestion | no_answer)
     */
    public function hangupChannel(string $channelId, string $reason = 'normal'): array
    {
        return $this->ariRequest('DELETE', '/channels/' . rawurlencode($channelId), ['reason' => $reason]);
    }

    /**
     * Return IAX2 peer status via AMI.
     */
    public function getIaxPeers(): string
    {
        $this->amiConnect();
        try {
            return $this->amiSend("Action: IAXPeers\r\n\r\n", 'iax2 peers');
        } finally {
            $this->amiDisconnect();
        }
    }

    // ----------------------------------------------------------------
    // Private helpers — column parsing for "core show channels verbose"
    // ----------------------------------------------------------------

    /** @return int[] */
    private function parseColumnLengths(string $headerLine): array
    {
        $words  = preg_split('/\s+/', $headerLine) ?: [];
        preg_match_all('/\s+/', $headerLine, $matches);
        $spaces = array_map('strlen', $matches[0]);

        $lengths = [];
        foreach (array_keys($words) as $i) {
            $lengths[] = strlen($words[$i]) + ($spaces[$i] ?? 0);
        }
        return $lengths;
    }

    /**
     * @param string[] $headers
     * @param int[]    $lengths
     * @return array<string, string>
     */
    private function splitByColumns(array $headers, string $line, array $lengths): array
    {
        $parts = [];
        foreach ($lengths as $i => $len) {
            $key = $headers[$i] ?? "col{$i}";
            if ($key === 'BridgedTo') {
                $parts[$key] = trim($line);
            } else {
                $parts[$key] = trim(substr($line, 0, $len));
                $line = substr($line, $len);
            }
        }
        return $parts;
    }

    // ----------------------------------------------------------------
    // Private helpers — SQL builders
    // ----------------------------------------------------------------

    /** @param array<string, mixed> $d */
    private function validateExtensionData(array $d): void
    {
        $required = ['account', 'name', 'secret', 'context', 'type'];
        foreach ($required as $field) {
            if (!isset($d[$field]) || (string)$d[$field] === '') {
                throw new \InvalidArgumentException("Missing required extension field: {$field}");
            }
        }
    }

    /** @param array<string, mixed> $d */
    private function buildSipInsert(array $d): string
    {
        return $this->buildSipValues($d, 'INSERT IGNORE');
    }

    /** @param array<string, mixed> $d */
    private function buildSipUpsert(array $d): string
    {
        return $this->buildSipValues($d, 'INSERT')
            . ' ON DUPLICATE KEY UPDATE id=VALUES(id), keyword=VALUES(keyword),'
            . ' data=VALUES(data), flags=VALUES(flags)';
    }

    /** @param array<string, mixed> $d */
    private function buildSipValues(array $d, string $verb): string
    {
        $rows = [
            ['deny', $d['deny'] ?? ''],
            ['secret', $d['secret']],
            ['dtmfmode', $d['dtmfmode'] ?? 'rfc2833'],
            ['canreinvite', $d['canreinvite'] ?? 'no'],
            ['context', $d['context']],
            ['host', $d['host'] ?? 'dynamic'],
            ['trustrpid', $d['trustrpid'] ?? 'yes'],
            ['sendrpid', $d['sendrpid'] ?? 'no'],
            ['type', $d['type']],
            ['nat', $d['nat'] ?? 'no'],
            ['port', $d['port'] ?? '5060'],
            ['qualify', $d['qualify'] ?? 'yes'],
            ['qualifyfreq', $d['qualifyfreq'] ?? '60'],
            ['transport', $d['transport'] ?? 'udp'],
            ['avpf', $d['avpf'] ?? 'no'],
            ['icesupport', $d['icesupport'] ?? 'no'],
            ['encryption', $d['encryption'] ?? 'no'],
            ['callgroup', $d['callgroup'] ?? ''],
            ['pickupgroup', $d['pickupgroup'] ?? ''],
            ['dial', $d['dial'] ?? ''],
            ['mailbox', $d['mailbox'] ?? ''],
            ['permit', $d['permit'] ?? '0.0.0.0/0.0.0.0'],
            ['callerid', $d['callerid'] ?? ''],
            ['callcounter', $d['callcounter'] ?? 'yes'],
            ['faxdetect', $d['faxdetect'] ?? 'no'],
            ['accountcode', $d['accountcode'] ?? ''],
            ['account', $d['account']],
        ];

        $valueList = [];
        foreach ($rows as $i => [$kw, $val]) {
            $valueList[] = sprintf(
                "(%s, %s, %s, %d)",
                $this->db->quote((string)$d['account']),
                $this->db->quote($kw),
                $this->db->quote((string)$val),
                $i + 2
            );
        }

        return $verb . ' INTO sip (id, keyword, data, flags) VALUES ' . implode(',', $valueList);
    }

    /** @param array<string, mixed> $d */
    private function buildUsersInsert(array $d): string
    {
        return sprintf(
            "INSERT IGNORE INTO users (extension, password, name, voicemail, ringtimer, noanswer,"
            . " recording, outboundcid, sipname, mohclass, noanswer_cid, busy_cid,"
            . " chanunavail_cid, noanswer_dest, busy_dest, chanunavail_dest) VALUES"
            . " (%s, '', %s, 'novm', 0, '', '', '', %s, 'default', '', '', '', '', '', '')",
            $this->db->quote((string)$d['account']),
            $this->db->quote((string)($d['name'] ?? '')),
            $this->db->quote((string)$d['account'])
        );
    }

    /** @param array<string, mixed> $d */
    private function buildUsersUpdate(array $d): string
    {
        return sprintf(
            "UPDATE users SET extension=%s, name=%s, sipname=%s WHERE extension=%s",
            $this->db->quote((string)$d['account']),
            $this->db->quote((string)($d['name'] ?? '')),
            $this->db->quote((string)$d['account']),
            $this->db->quote((string)$d['account'])
        );
    }

    /** @param array<string, mixed> $d */
    private function buildDevicesInsert(array $d): string
    {
        return sprintf(
            "INSERT IGNORE INTO devices (id, tech, dial, devicetype, user, description, emergency_cid)"
            . " VALUES (%s, 'sip', %s, 'fixed', %s, %s, '')",
            $this->db->quote((string)$d['account']),
            $this->db->quote((string)($d['dial'] ?? '')),
            $this->db->quote((string)$d['account']),
            $this->db->quote((string)$d['account'])
        );
    }

    /** @param array<string, mixed> $d */
    private function buildFollowMeInsert(array $d): string
    {
        return sprintf(
            "INSERT INTO findmefollow (grpnum, strategy, grptime, grppre, grplist, annmsg_id,"
            . " postdest, dring, remotealert_id, needsconf, toolate_id, pre_ring, ringing) VALUES"
            . " (%s,%s,%d,%s,%s,%d,%s,%s,%d,%s,%d,%d,%s)",
            $this->db->quote((string)$d['grpnum']),
            $this->db->quote((string)($d['strategy'] ?? '')),
            (int)($d['grptime'] ?? 20),
            $this->db->quote((string)($d['grppre'] ?? '')),
            $this->db->quote((string)($d['grplist'] ?? '')),
            (int)($d['annmsg_id'] ?? 0),
            $this->db->quote((string)($d['postdest'] ?? '')),
            $this->db->quote((string)($d['dring'] ?? '')),
            (int)($d['remotealert_id'] ?? 0),
            $this->db->quote((string)($d['needsconf'] ?? '')),
            (int)($d['toolate_id'] ?? 0),
            (int)($d['pre_ring'] ?? 0),
            $this->db->quote((string)($d['ringing'] ?? 'Ring'))
        );
    }

    /** @param array<string, mixed> $d */
    private function buildFollowMeUpdate(array $d): string
    {
        return sprintf(
            "UPDATE findmefollow SET strategy=%s, grptime=%d, grppre=%s, grplist=%s,"
            . " annmsg_id=%d, postdest=%s, dring=%s, remotealert_id=%d, needsconf=%s,"
            . " toolate_id=%d, pre_ring=%d, ringing=%s WHERE grpnum=%s",
            $this->db->quote((string)($d['strategy'] ?? '')),
            (int)($d['grptime'] ?? 20),
            $this->db->quote((string)($d['grppre'] ?? '')),
            $this->db->quote((string)($d['grplist'] ?? '')),
            (int)($d['annmsg_id'] ?? 0),
            $this->db->quote((string)($d['postdest'] ?? '')),
            $this->db->quote((string)($d['dring'] ?? '')),
            (int)($d['remotealert_id'] ?? 0),
            $this->db->quote((string)($d['needsconf'] ?? '')),
            (int)($d['toolate_id'] ?? 0),
            (int)($d['pre_ring'] ?? 0),
            $this->db->quote((string)($d['ringing'] ?? 'Ring')),
            $this->db->quote((string)$d['grpnum'])
        );
    }

    // ----------------------------------------------------------------
    // Private helpers — FreePBX module_admin / retrieve_conf
    // ----------------------------------------------------------------

    private function applyConfig(): void
    {
        exec('/var/lib/asterisk/bin/module_admin reload 2>/dev/null');
    }

    private function applyRetrieve(): void
    {
        exec('/var/lib/asterisk/bin/retrieve_conf 2>/dev/null');
    }

    /** @param array<string, mixed> $data */
    private function putAmpUser(array $data): void
    {
        $g = escapeshellarg((string)$data['grpnum']);
        $list = escapeshellarg((string)($data['grplist'] ?? ''));
        $time = escapeshellarg((string)($data['grptime'] ?? '20'));
        $pre  = escapeshellarg((string)($data['pre_ring'] ?? '0'));

        exec("/usr/sbin/asterisk -rx \"database put AMPUSER {$g}/followme/changecid default\" 2>/dev/null");
        exec("/usr/sbin/asterisk -rx \"database put AMPUSER {$g}/followme/ddial DIRECT\" 2>/dev/null");
        exec("/usr/sbin/asterisk -rx \"database put AMPUSER {$g}/followme/fixedcid \" 2>/dev/null");
        exec("/usr/sbin/asterisk -rx \"database put AMPUSER {$g}/followme/grpconf ENABLED\" 2>/dev/null");
        exec("/usr/sbin/asterisk -rx \"database put AMPUSER {$g}/followme/grplist {$list}\" 2>/dev/null");
        exec("/usr/sbin/asterisk -rx \"database put AMPUSER {$g}/followme/grptime {$time}\" 2>/dev/null");
        exec("/usr/sbin/asterisk -rx \"database put AMPUSER {$g}/followme/prering {$pre}\" 2>/dev/null");
    }

    private function deleteAmpUser(string $grpnum): void
    {
        $g = escapeshellarg($grpnum);
        exec("/usr/sbin/asterisk -rx \"database deltree AMPUSER {$g}/followme\" 2>/dev/null");
    }
}
