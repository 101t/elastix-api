<?php 
include_once dirname(__FILE__)."/lib/ast/AsteriskManager.php";
include_once dirname(__FILE__)."/lib/ini/ini_handler.php";
include_once dirname(__FILE__)."/lib/json/phpJson.class.php";

class Asterisk{
	public function __construct($DEBUG=false)
	{
		$this->DEBUG = $DEBUG;
		$this->ast = new Net_AsteriskManager(array('server' => '127.0.0.1', 'port' => '5038'));
		$auth = $this->get_auth();
		$this->username = $auth["username"];
		$this->password = $auth["password"];
	}
	private function get_auth(){
		$data = parse_ini("/etc/asterisk/manager.conf");
		$keys = array_keys($data);
		$username = "";
		$password = "";//$data["admin"]["secret"];
		if (isset($data[$keys[1]])) {
			$username = $keys[1];
			$password = $data[$keys[1]]["secret"];
		}
		$auth = array("username" => $username, "password" => $password);
		if ($this->DEBUG) {
			echo "AUTH: ".json_encode($auth)."\n";
		}
		return $auth;
	}
	public function get_sip_peers(){
		try{
			$this->ast->connect();
			$this->ast->login($this->username, $this->password);
			$data = $this->ast->getSipPeers();
			$arr = explode("\n", $data);
			$length = count($arr);
			$mainarr = array();
			$namearr = "";
			$allow_checkname = 0;
			$myarr = array();
			$counter = 0;
			foreach ($arr as $key => $value) {
				if(strlen($value) > 1){
					//$do = '"'.$value.'"';//str_replace("\n", '", "', $value);
					$value = str_replace("\r", "", $value);
					$doarr = split(": ", $value);
					$myarr[(string)$doarr[0]] = (string)$doarr[1];
					//echo $doarr[0];
					if(($doarr[0] === "ObjectName") && ($allow_checkname === $counter)){
						$namearr = $doarr[1]."_".$counter;
						$allow_checkname = $allow_checkname + 1;
					} else {
						$namearr = "check ".$counter;
					}
				} else {
					if (!empty($myarr) && (count($myarr) > 3)) {
						$mainarr[] = $myarr;
					}
					$myarr = array();
					$counter = $counter + 1;
					$allow_checkname = true;
				}
			}
			header('Content-Type: application/json');
			echo json_encode($mainarr);
			if ($this->DEBUG) {
				echo "SIP PEERS: ".json_encode($mainarr)."\n";
			}
		} catch (PEAR_Exception $e) {
			echo $e->getMessage();
		}
	}
	public function get_sip_extensions(){
		$data = parse_ini("/etc/asterisk/sip_additional.conf");
		header('Content-Type: application/json');
		echo json_encode($data);
	}
	public function get_channel_status(){
		$this->ast->connect();
		$this->ast->login($this->username, $this->password);
		$data = $this->ast->getChannelStatus();
		header('Content-Type: text/html');
		print_r($data);
	}
	public function get_iax_peers(){
		$this->ast->connect();
		$this->ast->login($this->username, $this->password);
		$data = $this->ast->getIaxPeers();
		header('Content-Type: application/json');
		print_r($data);
	}
	public function get_parked_calls(){
		$this->ast->connect();
		$this->ast->login($this->username, $this->password);
		$data = $this->ast->parkedCalls();
		header('Content-Type: text/html');
		print_r($data);
	}
	public function get_system_resources(){
		/*
			11:14:07 up  2:43,  5 users,  load average: 0.14, 0.31, 0.38
			11:39:13 up 8 days, 21:44,  2 users,  load average: 0.08, 0.10, 0.03
			14:55:03 up 9 days,  1:00,  2 users,  load average: 0.16, 0.03, 0.01
		*/
		exec("uptime", $data);
		//print_r($data[0]);
		$data = str_replace("load average: ", "", $data[0]);
		$data = str_replace(" users", "", $data);
		$data = str_replace("days,", "days", $data);
		$matches = explode(",  ", $data);
		$matches = array_map("trim", $matches);
		$mainarr = array();
		$mainarr["datetime"] = $matches[0];
		$mainarr["users"] = $matches[1];
		$mainarr["usage"] = $matches[2];
		header('Content-Type: application/json');
		//print_r($mainarr);
		echo json_encode($mainarr);
	}
	private function _get_header($line, $opt){
		$temp = preg_split("/(\s+)/", $line, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		$space_lens = array();
		/*$words = array_reduce($temp, function(&$result, $item) use (&$space_lens) {
			if(strlen( trim($item)) === 0){
				$space_lens[] = strlen($item);
			} else {
				$result[] = $item;
			}
			return $result;
		}, array());*/
		$words = preg_split('/\s+/', $line);
		preg_match_all('/\s+/', $line, $matches);
		$space_lens = array_map('strlen', $matches[0]);
		$char_lens = array();
		foreach (array_keys($words) as $key) {
			//$char_lens[$key] = (isset($words[$key]) ? strlen($words[$key]) : 0) + (isset($space_lens[$key]) ? $space_lens[$key] : 0);
			$char_lens[] = @(strlen($words[$key]) + $space_lens[$key]);
		}
		$temp_arr = array();
		switch ($opt) {
			case "char_lens":
				$temp_arr = $char_lens;
				break;
			case "space_lens":
				$temp_arr = $space_lens;
				break;
			case "words":
				$temp_arr = $words;
				break;
			default:
				$temp_arr = $words;
				break;
		}
		return $temp_arr;
	}

	private function _get_parts($words, $string, $positions){
		$parts = array();

	    foreach ($positions as $key => $position){
	    	if ($words[$key] == "BridgedTo") {
	    		$parts[$words[$key]] = trim($string);
	    	} else {
	    		$parts[$words[$key]] = trim(substr($string, 0, $position));	
	    	}
	        $string = substr($string, $position);
	    }

	    return $parts;
	}

	public function get_active_call(){
		exec("/usr/sbin/asterisk -rx \"core show channels verbose\"", $data);
		//print_r($data);
		$headers_arr = $this->_get_header($data[0], "words");
		$char_lens = $this->_get_header($data[0], "char_lens");
		unset($data[0]);
		$mainarr = array();
		$myarr = array();
		foreach ($data as $key => $value) {
			if (strlen($value) > 100) {
				$myarr = $this->_get_parts($headers_arr, $value, $char_lens);
				$mainarr[] = $myarr;
			}
			else{
				$mainarr[] = $value;	
			}
			$myarr = array();
		}
		header('Content-Type: application/json');
		echo json_encode($mainarr);
	}
}
?>