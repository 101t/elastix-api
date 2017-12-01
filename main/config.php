<?php 
include_once(dirname(__FILE__)."/lib/ini/ini_handler.php");
include_once(dirname(__FILE__)."/lib/json/phpJson.class.php");
class AuthManager {
	public function __construct()
	{
		try{
			$this->data = parse_ini("/etc/asterisk/manager.conf");	
		} catch (Exception $e) {
			echo $e->getMessage();	
			echo "The exception was created on line: " . $e->getLine();
			echo $e->getFile();
		}
	}
	public function get_auth(){
		$keys = array_keys($this->data);
		$username = "";
		$password = "";
		if (isset($this->data[$keys[1]])) {
			$username = $keys[1];
			$password = $this->data[$keys[1]]["secret"];
		}
		$auth = array("username" => $username, "password" => $password);
		return $auth;
	}
	public function get_auth_api(){
		$keys = array_keys($this->data);
		$username = "";
		$password = "";
		if (isset($this->data[$keys[1]])) {
			$username = $keys[1];
			$password = $this->data[$keys[1]]["secret"];
		}
		$auth = array('lordword' => base64_encode($username.":".$password));
		return $auth;
	}
	public function get_auth_json(){
		$auth = $this->get_auth_api();
		header('Content-Type: application/json');
		echo json_encode($auth);
	}
}
?>