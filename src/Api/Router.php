<?php

declare(strict_types=1);

namespace PbxApi\Api;

use PbxApi\Config\Config;
use PbxApi\Contracts\PbxDriverInterface;
use PbxApi\Drivers\AsteriskDriver;
use PbxApi\Drivers\IssabelDriver;
use PbxApi\Drivers\FreePBXDriver;

/**
 * Central HTTP router for the PBX API.
 *
 * Responsibilities:
 *  1. Authenticate the incoming request via the Authorization header.
 *  2. Instantiate the appropriate PBX driver based on PBX_DRIVER config.
 *  3. Dispatch the `cmd` query parameter to the correct driver method.
 *  4. Emit a JSON (or binary) HTTP response.
 */
class Router
{
    private Config $config;
    private PbxDriverInterface $driver;

    public function __construct(?Config $config = null)
    {
        $this->config = $config ?? Config::getInstance();
        $this->driver = $this->resolveDriver();
    }

    /**
     * Handle the current HTTP request.
     *
     * Entry point called from api.php.
     */
    public function handle(): void
    {
        if (!$this->authenticate()) {
            $this->json(['error' => 'Unauthorized', 'code' => 401], 401);
            return;
        }

        $cmd = isset($_GET['cmd']) ? (string)$_GET['cmd'] : '';

        if ($cmd === '') {
            $this->json(['error' => 'cmd parameter is required', 'code' => 400], 400);
            return;
        }

        try {
            $this->dispatch($cmd);
        } catch (\InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage(), 'code' => 400], 400);
        } catch (\RuntimeException $e) {
            $this->json(['error' => $e->getMessage(), 'code' => 503], 503);
        } catch (\Throwable $e) {
            $this->json(['error' => 'Internal server error', 'code' => 500], 500);
        }
    }

    // ----------------------------------------------------------------
    // Command dispatcher
    // ----------------------------------------------------------------

    private function dispatch(string $cmd): void
    {
        switch ($cmd) {
            // --- Auth ---
            case 'auth':
                $this->json($this->resolveAuth());
                break;

            // --- Peers / endpoints ---
            case 'sippeers':
                $this->json($this->driver->getSipPeers());
                break;

            case 'sipextensions':
                $this->json($this->driver->getSipExtensions());
                break;

            // --- Active calls ---
            case 'activecall':
                $this->json($this->driver->getActiveCalls());
                break;

            case 'channelstatus':
                $channel = isset($_GET['channel']) ? (string)$_GET['channel'] : null;
                $this->text($this->driver->getChannelStatus($channel));
                break;

            case 'parkedcalls':
                $this->text($this->driver->getParkedCalls());
                break;

            // --- System ---
            case 'systemresources':
                $this->json($this->driver->getSystemResources());
                break;

            case 'getharddrivers':
                $this->json($this->driver->getDiskUsage());
                break;

            case 'getiptablesstatus':
                $this->json($this->getIptablesStatus());
                break;

            // --- CDR ---
            case 'cdrreport':
                $filters = [
                    'start_date'    => $_POST['start_date'] ?? null,
                    'end_date'      => $_POST['end_date'] ?? null,
                    'field_name'    => $_POST['field_name'] ?? null,
                    'field_pattern' => $_POST['field_pattern'] ?? null,
                    'status'        => $_POST['status'] ?? 'ALL',
                    'limit'         => isset($_POST['limit']) ? (int)$_POST['limit'] : 100,
                    'custom'        => $_POST['custom'] ?? null,
                ];
                $this->json($this->driver->getCdr($filters));
                break;

            // --- Recordings ---
            case 'getwavfile':
                $this->serveWavFile();
                break;

            // --- Extension CRUD ---
            case 'addextension':
                $ok = $this->driver->addExtension($_POST);
                $this->json(['status' => $ok ? 'INSERT OK' : 'ALREADY EXISTS', 'code' => 200]);
                break;

            case 'updateextension':
                $this->driver->updateExtension($_POST);
                $this->json(['status' => 'UPDATE OK', 'code' => 200]);
                break;

            case 'deleteextension':
                $this->driver->deleteExtension((string)($_POST['account'] ?? ''));
                $this->json(['status' => 'DELETE OK', 'code' => 200]);
                break;

            // --- Follow-Me CRUD ---
            case 'addfollowmeextension':
                $ok = $this->driver->addFollowMe($_POST);
                $this->json(['status' => $ok ? 'INSERT OK' : 'ALREADY EXISTS', 'code' => 200]);
                break;

            case 'updatefollowmeextension':
                $this->driver->updateFollowMe($_POST);
                $this->json(['status' => 'UPDATE OK', 'code' => 200]);
                break;

            case 'deletefollowmeextension':
                $this->driver->deleteFollowMe((string)($_POST['grpnum'] ?? ''));
                $this->json(['status' => 'DELETE OK', 'code' => 200]);
                break;

            case 'getfollowmeextension':
                $this->json($this->driver->getFollowMe((string)($_POST['grpnum'] ?? '')));
                break;

            case 'getallfollowmeextensions':
                $this->json($this->driver->getAllFollowMe());
                break;

            // --- ARI channels (Asterisk-specific) ---
            case 'arichannels':
                if ($this->driver instanceof AsteriskDriver) {
                    $this->json($this->driver->getAriChannels());
                } else {
                    $this->json(['error' => 'ARI not supported by active driver', 'code' => 501], 501);
                }
                break;

            // --- Originate call (Asterisk/Issabel/FreePBX) ---
            case 'originatecall':
                if ($this->driver instanceof AsteriskDriver) {
                    $ok = $this->driver->originateCall(
                        (string)($_POST['channel'] ?? ''),
                        (string)($_POST['extension'] ?? ''),
                        (string)($_POST['context'] ?? 'from-internal'),
                        (string)($_POST['callerid'] ?? ''),
                        (int)($_POST['timeout'] ?? 30000)
                    );
                    $this->json(['status' => $ok ? 'CALL INITIATED' : 'FAILED', 'code' => 200]);
                } else {
                    $this->json(['error' => 'originatecall not supported by active driver', 'code' => 501], 501);
                }
                break;

            // --- FreePBX queue management ---
            case 'getqueues':
                if ($this->driver instanceof FreePBXDriver) {
                    $this->json($this->driver->getQueues());
                } else {
                    $this->json(['error' => 'getqueues requires FreePBX driver', 'code' => 501], 501);
                }
                break;

            case 'queueaddmember':
                if ($this->driver instanceof FreePBXDriver) {
                    $ok = $this->driver->queueAddMember(
                        (string)($_POST['queue'] ?? ''),
                        (string)($_POST['device'] ?? ''),
                        (int)($_POST['penalty'] ?? 0)
                    );
                    $this->json(['status' => $ok ? 'MEMBER ADDED' : 'FAILED', 'code' => 200]);
                } else {
                    $this->json(['error' => 'queueaddmember requires FreePBX driver', 'code' => 501], 501);
                }
                break;

            case 'queueremovemember':
                if ($this->driver instanceof FreePBXDriver) {
                    $ok = $this->driver->queueRemoveMember(
                        (string)($_POST['queue'] ?? ''),
                        (string)($_POST['device'] ?? '')
                    );
                    $this->json(['status' => $ok ? 'MEMBER REMOVED' : 'FAILED', 'code' => 200]);
                } else {
                    $this->json(['error' => 'queueremovemember requires FreePBX driver', 'code' => 501], 501);
                }
                break;

            default:
                $this->json(['error' => "Unknown command: {$cmd}", 'code' => 404], 404);
        }
    }

    // ----------------------------------------------------------------
    // Authentication
    // ----------------------------------------------------------------

    private function authenticate(): bool
    {
        $expected = $this->config->get('API_SECRET_KEY');

        // Accept key only from the Authorization header to avoid leaking the
        // secret via URL query parameters (which are routinely logged by web
        // servers and proxies).
        $provided = $this->getAuthorizationHeader() ?? '';

        if ($provided === '' || $expected === 'YOUR_SECRETKEY_50_RANDOM_CHARS') {
            return false;
        }

        return hash_equals($expected, $provided);
    }

    private function getAuthorizationHeader(): ?string
    {
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            return (string)$_SERVER['HTTP_AUTHORIZATION'];
        }
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (isset($headers['Authorization'])) {
                return (string)$headers['Authorization'];
            }
        }
        return null;
    }

    // ----------------------------------------------------------------
    // Driver factory
    // ----------------------------------------------------------------

    private function resolveDriver(): PbxDriverInterface
    {
        switch ($this->config->get('PBX_DRIVER', 'asterisk')) {
            case 'issabel':
                return new IssabelDriver($this->config);
            case 'freepbx':
                return new FreePBXDriver($this->config);
            case 'asterisk':
            default:
                return new AsteriskDriver($this->config);
        }
    }

    // ----------------------------------------------------------------
    // Auth response helper
    // ----------------------------------------------------------------

    /** @return array{lordword: string} */
    private function resolveAuth(): array
    {
        [$username, $secret] = $this->resolveAmiCredentialsForAuth();
        return ['lordword' => base64_encode($username . ':' . $secret)];
    }

    /** @return array{0: string, 1: string} */
    private function resolveAmiCredentialsForAuth(): array
    {
        $username = $this->config->get('AMI_USERNAME');
        $secret   = $this->config->get('AMI_SECRET');

        if ($username !== '' && $secret !== '') {
            return [$username, $secret];
        }

        $confPath = $this->config->get('AMI_CONF_PATH', '/etc/asterisk/manager.conf');
        if (!is_readable($confPath)) {
            return [$username, $secret];
        }

        $ini = @parse_ini_file($confPath, true, INI_SCANNER_RAW);
        if (!is_array($ini)) {
            return [$username, $secret];
        }

        foreach (array_keys($ini) as $section) {
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
    // Misc command implementations
    // ----------------------------------------------------------------

    /** @return array{pid: string, is_exist: bool} */
    private function getIptablesStatus(): array
    {
        $out = [];
        exec('sudo /sbin/service iptables status 2>&1', $out);
        $output = implode("\n", $out);
        return [
            'pid'      => $output,
            'is_exist' => strlen($output) > 100,
        ];
    }

    private function serveWavFile(): void
    {
        $name             = isset($_GET['name']) ? (string)$_GET['name'] : '';
        $directory        = rtrim($this->config->get('RECORDINGS_PATH', '/var/spool/asterisk/monitor'), '/');
        $directoryRealpath = realpath($directory);

        if ($name === '' || $directoryRealpath === false) {
            $this->json(['status' => 'File not found', 'code' => 404], 404);
            return;
        }

        // Prevent path traversal: realpath must be inside the configured directory.
        // Using a trailing separator in the prefix check prevents matching
        // sibling directories that share a common path prefix (e.g. /monitor2/).
        $file = realpath($directoryRealpath . DIRECTORY_SEPARATOR . ltrim($name, '/'));

        if (
            $file !== false
            && str_starts_with($file, $directoryRealpath . DIRECTORY_SEPARATOR)
            && file_exists($file)
            && is_file($file)
        ) {
            header('Content-Disposition: attachment; filename="' . basename($file) . '"');
            header('Content-Length: ' . filesize($file));
            header('Content-Type: application/octet-stream');
            readfile($file);
        } else {
            $this->json(['status' => 'File not found', 'code' => 404], 404);
        }
    }

    // ----------------------------------------------------------------
    // Response helpers
    // ----------------------------------------------------------------

    /** @param array<mixed> $data */
    private function json(array $data, int $httpCode = 200): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json');
        $this->setCorsHeaders();
        echo json_encode($data);
    }

    private function text(string $data, int $httpCode = 200): void
    {
        http_response_code($httpCode);
        header('Content-Type: text/plain');
        $this->setCorsHeaders();
        echo $data;
    }

    private function setCorsHeaders(): void
    {
        $origins = $this->config->get('CORS_ALLOWED_ORIGINS', '*');
        header('Access-Control-Allow-Origin: ' . $origins);
    }
}
