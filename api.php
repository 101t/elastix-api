<?php

/**
 * PBX API — main entry point.
 *
 * Supports three PBX back-ends (set PBX_DRIVER in .env):
 *   asterisk  — modern Asterisk 13+ with PJSIP / chan_sip (default)
 *   issabel   — Issabel PBX (community successor to Elastix)
 *   freepbx   — FreePBX (most widely deployed Asterisk GUI)
 *
 * See .env.example for all available configuration keys.
 */

// ---- Autoloader -------------------------------------------------------
// When installed via Composer, use the generated autoloader.
// When used without Composer, fall back to the legacy include-based loader.

$composerAutoload = __DIR__ . '/vendor/autoload.php';

if (file_exists($composerAutoload)) {
    require $composerAutoload;

    use PbxApi\Config\Config;
    use PbxApi\Api\Router;

    Config::getInstance(__DIR__);
    (new Router())->handle();
} else {
    // ---- Legacy fallback (no Composer) --------------------------------
    // Provides the same endpoints as the original api.php so existing
    // integrations continue to work without requiring a Composer install.
    require_once __DIR__ . '/main/asterisk.php';
    require_once __DIR__ . '/main/elastix.php';
    require_once __DIR__ . '/main/config.php';

    /**
     * @deprecated Use PbxApi\Api\Router (Composer-based) instead.
     */
    class APICommand
    {
        /** @var string */
        private $key;
        /** @var string */
        private $rkey;
        /** @var string */
        private $cmd;

        public function __construct(string $rkey, string $cmd)
        {
            $this->key  = (string)(getenv('API_SECRET_KEY') ?: 'YOUR_SECRETKEY_50_RANDOM_CHARS');
            $this->rkey = $rkey;
            $this->cmd  = $cmd;
        }

        public function execute(): void
        {
            if (!hash_equals($this->key, $this->rkey)) {
                echo 'key not matched';
                return;
            }

            switch ($this->cmd) {
                case 'auth':
                    $auth = new AuthManager();
                    $auth->get_auth_json();
                    break;
                case 'sippeers':
                    $ast = new Asterisk();
                    $ast->get_sip_peers();
                    break;
                case 'sipextensions':
                    $ast = new Asterisk();
                    $ast->get_sip_extensions();
                    break;
                case 'activecall':
                    $ast = new Asterisk();
                    $ast->get_active_call();
                    break;
                case 'systemresources':
                    $ast = new Asterisk();
                    $ast->get_system_resources();
                    break;
                case 'parkedcalls':
                    $ast = new Asterisk();
                    $ast->get_parked_calls();
                    break;
                case 'channelstatus':
                    $ast = new Asterisk();
                    $ast->get_channel_status();
                    break;
                case 'cdrreport':
                    $ela = new Elastix();
                    $ela->get_cdr();
                    break;
                case 'getwavfile':
                    $ela = new Elastix();
                    $ela->get_wav_file();
                    break;
                case 'getharddrivers':
                    $ela = new Elastix();
                    $ela->get_harddrivers();
                    break;
                case 'getiptablesstatus':
                    $ela = new Elastix();
                    $ela->get_iptables_status();
                    break;
                case 'addextension':
                    $ela = new Elastix();
                    $ela->add_sip_extension();
                    break;
                case 'updateextension':
                    $ela = new Elastix();
                    $ela->update_sip_extension();
                    break;
                case 'deleteextension':
                    $ela = new Elastix();
                    $ela->delete_sip_extension();
                    break;
                case 'addfollowmeextension':
                    $ela = new Elastix();
                    $ela->add_followme_extension();
                    break;
                case 'updatefollowmeextension':
                    $ela = new Elastix();
                    $ela->update_followme_extension();
                    break;
                case 'deletefollowmeextension':
                    $ela = new Elastix();
                    $ela->delete_followme_extension();
                    break;
                case 'getfollowmeextension':
                    $ela = new Elastix();
                    $ela->view_followme_extension();
                    break;
                case 'getallfollowmeextensions':
                    $ela = new Elastix();
                    $ela->view_followme_all_extensions();
                    break;
                default:
                    echo 'cmd not matched';
                    break;
            }
        }
    }

    if (isset($_GET['cmd'])) {
        $cmd     = (string)$_GET['cmd'];
        $headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
        $rkey    = isset($headers['Authorization']) ? (string)$headers['Authorization'] : '';

        if ($rkey === '') {
            echo 'key not found';
        } else {
            (new APICommand($rkey, $cmd))->execute();
        }
    } else {
        echo 'test';
    }
}
