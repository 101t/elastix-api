<?php 
include dirname(__FILE__)."/main/asterisk.php";
include dirname(__FILE__)."/main/elastix.php";
include dirname(__FILE__)."/main/config.php";
class APICommand
{
	public function __construct($rkey, $cmd)
	{
		$this->key = 'YOUR_SECRETKEY_50_RANDOM_CHARS';
		$this->rkey = $rkey;
		$this->cmd = $cmd;
	}
	public function execute(){
		if (strcmp($this->key, $this->rkey)) {
			switch ($this->cmd) {
				case "auth":
					$auth = new AuthManager();
					$auth->get_auth_json();
					break;
				case "sippeers":
					$ast = new Asterisk();
					$ast->get_sip_peers();
					break;
				case "sipextensions":
					$ast = new Asterisk();
					$ast->get_sip_extensions();
					break;
				case "activecall":
					$ast = new Asterisk();
					$ast->get_active_call();
					break;
				case "systemresources":
					$ast = new Asterisk();
					$ast->get_system_resources();
					break;
				case "parkedcalls":
					$ast = new Asterisk();
					$ast->get_parked_calls();
					break;
				case "channelstatus":
					$ast = new Asterisk();
					$ast->get_channel_status();
					break;
				case "cdrreport":
					$ela = new Elastix();
					$ela->get_cdr();
					break;
				case "getwavfile":
					$ela = new Elastix();
					$ela->get_wav_file();
					break;
				case "getharddrivers":
					$ela = new Elastix();
					$ela->get_harddrivers();
					break;
				case "getiptablesstatus":
					$ela = new Elastix();
					$ela->get_iptables_status();
					break;
				case "addextension":
					$ela = new Elastix();
					$ela->add_sip_extension();
					break;
				case "updateextension":
					$ela = new Elastix();
					$ela->update_sip_extension();
					break;
				case "deleteextension":
					$ela = new Elastix();
					$ela->delete_sip_extension();
					break;
				case "addfollowmeextension":
					$ela = new Elastix();
					$ela->add_followme_extension();
					break;
				case "updatefollowmeextension":
					$ela = new Elastix();
					$ela->update_followme_extension();
					break;
				case "deletefollowmeextension":
					$ela = new Elastix();
					$ela->delete_followme_extension();
					break;
				case "getfollowmeextension":
					$ela = new Elastix();
					$ela->view_followme_extension();
					break;
				case "getallfollowmeextensions":
					$ela = new Elastix();
					$ela->view_followme_all_extensions();
					break;
				default:
					echo "cmd not matched";
					break;
			}
		} else {
			echo "key not matched";
		}
	}
}
if (isset($_GET["cmd"])) {
	$cmd = $_GET["cmd"];
	$headers = apache_request_headers();
	if (isset($headers["Authorization"])) {
		$rkey = (string)$headers["Authorization"];
		$obj = new APICommand($rkey, $cmd);
		$obj->execute();
	} else {
		echo "key not found";
	}
} else {
	echo "test";
}
?>
