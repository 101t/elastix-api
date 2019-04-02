<?php 
include_once dirname(__FILE__)."/lib/ini/ini_handler.php";
include_once dirname(__FILE__)."/lib/json/phpJson.class.php";
include_once dirname(__FILE__)."/lib/ast/Extension.php";
class Elastix{
	public function __construct(){
		$fh = fopen('/etc/elastix.conf','r');
		$data = array();
		while ($line = fgets($fh)) {
			if(strlen($line) > 1){
				$doarr = split("=", $line);
				$passwd = (string)$doarr[1];
				$passwd = str_replace("\n", "", $passwd);
				$data[(string)$doarr[0]] = $passwd;
			}
		}
		fclose($fh);
		$this->hostname = "localhost";
		$this->username = "root";
		$this->password = $data["mysqlrootpwd"];
		$this->db = null;
	}
	public function __destruct(){
		try {
			$this->db = null;
		} catch (PDOException $e) {
			echo $e->getMessage();
		}
	}
	private function _get_db_connection($dbname){
		try {
			$this->db = new PDO("mysql:host=".$this->hostname.";dbname=".$dbname.";charset=utf8", $this->username, $this->password);
			$this->db->query("SET CHARACTER SET utf8");
		} catch (PDOException $e) {
			echo $e->getMessage();
		}
	}
	private function _cdr_where_expression($start_date, $end_date, $field_name, $field_pattern, $status, $custom){
		$where = "";
		if (!is_null($start_date) && !is_null($end_date))
			$where .= "(calldate BETWEEN '$start_date' AND '$end_date')";

		if ( !is_null($field_name) && !is_null($field_pattern)) {
			$where = (empty($where))? $where : "$where AND ";
			$where .= "($field_name LIKE '%$field_pattern%')";
		}

		$where = (empty($where))? $where : "$where AND ";
		$where .= (is_null($status) || empty($status) || $status === "ALL") ? "(disposition IN ('ANSWERED', 'BUSY', 'FAILED', 'NO ANSWER'))" : "(disposition = '$status')";
		$where .= " AND dst != 's' ";

		if(!is_null($custom))
			$where .= $custom;

		return $where;
	}
	public function get_cdr(){
		/*
			+---------------+
			| COLUMN_NAME   |
			+---------------+
			| calldate      | 
			| clid          | 
			| src           | 
			| dst           | 
			| dcontext      | 
			| channel       | 
			| dstchannel    | 
			| lastapp       | 
			| lastdata      | 
			| duration      | 
			| billsec       | 
			| disposition   | 
			| amaflags      | 
			| accountcode   | 
			| uniqueid      | 
			| userfield     | 
			| recordingfile | 
			| cnum          | 
			| cnam          | 
			| outbound_cnum | 
			| outbound_cnam | 
			| dst_cnam      | 
			| did           | 
			+---------------+
		*/
		try {
			$this->_get_db_connection("asteriskcdrdb");
		 	$start_date             = $_POST["start_date"];
			$end_date               = $_POST["end_date"];
			$field_name             = $_POST["field_name"];
			$field_pattern          = $_POST["field_pattern"];
			$status                 = $_POST["status"];
			$limit                  = isset($_POST["limit"])? $_POST["limit"] : 100;
			$custom                 = $_POST["custom"];
			$where_expression		= $this->_cdr_where_expression($start_date, $end_date, $field_name, $field_pattern, $status, $custom);
			$sql_cmd        = "SELECT * FROM cdr WHERE $where_expression ORDER BY calldate DESC LIMIT $limit";
			$stmt           = $this->db->prepare($sql_cmd);
			$stmt->execute();
		 	$result = (array)$stmt->fetchAll(PDO::FETCH_ASSOC);
		 	header('Content-Type: application/json');
		 	echo json_encode($result);
		} catch (Exception $e) {
			echo $e->getMessage();
		}
	}
	public function get_wav_file(){
		/*
			$name = "/2015/12/08/out-05355620760-101-20151208-102449-1449563089.106.wav";
		*/
		$name           = $_GET["name"];
                $directory      = "/var/spool/asterisk/monitor";
                $file = realpath($directory . $name);
                if(strpos($file, $directory) !== false && strpos($file, $directory) == 0 && file_exists($file) && is_file($file)){
			header("Content-Disposition: attachment; filename=\"" . basename($file) . "\"");
			header("Content-Length: " . filesize($file));
			header("Content-Type: application/octet-stream;");
			readfile($file);	
		} else {
			header("HTTP/1.0 404 Not Found");
			header("Content-Type: application/json");
			echo '{"status": "File not found", "code": 404}';
		}
		
	}
	public function get_harddrivers(){
		$main_arr = array();
		exec("df -H /", $harddisk);
		exec("du -sh /var/log", $logs);
		exec("du -sh /opt", $thirdparty);
		exec("du -sh /var/spool/asterisk/voicemail", $voicemails);
		exec("du -sh /var/www/backup", $backups);
		exec("du -sh /etc", $configuration);
		exec("du -sh /var/spool/asterisk/monitor", $recording);
		$hard_arr = array();
		$tmp_arr = explode(" ", trim(preg_replace("/\s\s+/", " ", $harddisk[2])));
		$hard_arr["size"] 		= $tmp_arr[0];
		$hard_arr["used"] 		= $tmp_arr[1];
		$hard_arr["avail"] 		= $tmp_arr[2];
		$hard_arr["usepercent"] = $tmp_arr[3];
		$hard_arr["mount"] 		= $tmp_arr[4];
		$main_arr["harddisk"] = $hard_arr;
		$main_arr["logs"] = explode("\t", $logs[0]);
		$main_arr["thirdparty"] = explode("\t", $thirdparty[0]);
		$main_arr["voicemails"] = explode("\t", $voicemails[0]);
		$main_arr["backups"] = explode("\t", $backups[0]);
		$main_arr["configuration"] = explode("\t", $configuration[0]);
		$main_arr["recording"] = explode("\t", $recording[0]);
		header("Content-Type: application/json");
		echo json_encode($main_arr);
	}
	public function get_iptables_status(){
		$exist = 'false';
		$pid = shell_exec("sudo /sbin/service iptables status 2>&1");
		if (strlen($pid) > 100) {
			$exist = 'true';
		}
		header("Content-Type: application/json");
		echo '{"pid": "'.$pid.'", "is_exist": '.$exist.'}';
	}
	private function apply_config(){
		exec("/var/lib/asterisk/bin/module_admin reload", $data);
	}
	public function add_sip_extension(){
		$this->_get_db_connection("asterisk");
		$dict = array(
			"name" => $_POST["name"],
			"deny" => $_POST["deny"],
			"secret" => $_POST["secret"],
			"dtmfmode" => $_POST["dtmfmode"],
			"canreinvite" => $_POST["canreinvite"],
			"context" => $_POST["context"],
			"host" => $_POST["host"],
			"trustrpid" => $_POST["trustrpid"],
			"sendrpid" => $_POST["sendrpid"],
			"type" => $_POST["type"],
			"nat" => $_POST["nat"],
			"port" => $_POST["port"],
			"qualify" => $_POST["qualify"],
			"qualifyfreq" => $_POST["qualifyfreq"],
			"transport" => $_POST["transport"],
			"avpf" => $_POST["avpf"],
			"icesupport" => $_POST["icesupport"],
			"encryption" => $_POST["encryption"],
			"callgroup" => $_POST["callgroup"],
			"pickupgroup" => $_POST["pickupgroup"],
			"dial" => $_POST["dial"],
			"mailbox" => $_POST["mailbox"],
			"permit" => $_POST["permit"],
			"callerid" => $_POST["callerid"],
			"callcounter" => $_POST["callcounter"],
			"faxdetect" => $_POST["faxdetect"],
			"account" => $_POST["account"]
		);
		$ext = new Extension($dict, "insert");
		$stmt0 = $this->db->prepare($ext->select_sip_sqlscript());
		$stmt0->execute();
		$row = $stmt0->fetch(PDO::FETCH_ASSOC);
		if(!$row){
			$stmt1 = $this->db->exec($ext->insert_into_users_sqlscript());
			$stmt2 = $this->db->exec($ext->insert_into_devices_sqlscript());
			$stmt3 = $this->db->exec($ext->insert_into_sip_sqlscript());
			$this->apply_config();
		}
		header('Content-Type: application/json');
		echo '{"status": "INSERT OK", "code": 200}';
	}
	public function update_sip_extension(){
		$this->_get_db_connection("asterisk");
		$dict = array(
			"name" => $_POST["name"],
			"deny" => $_POST["deny"],
			"secret" => $_POST["secret"],
			"dtmfmode" => $_POST["dtmfmode"],
			"canreinvite" => $_POST["canreinvite"],
			"context" => $_POST["context"],
			"host" => $_POST["host"],
			"trustrpid" => $_POST["trustrpid"],
			"sendrpid" => $_POST["sendrpid"],
			"type" => $_POST["type"],
			"nat" => $_POST["nat"],
			"port" => $_POST["port"],
			"qualify" => $_POST["qualify"],
			"qualifyfreq" => $_POST["qualifyfreq"],
			"transport" => $_POST["transport"],
			"avpf" => $_POST["avpf"],
			"icesupport" => $_POST["icesupport"],
			"encryption" => $_POST["encryption"],
			"callgroup" => $_POST["callgroup"],
			"pickupgroup" => $_POST["pickupgroup"],
			"dial" => $_POST["dial"],
			"mailbox" => $_POST["mailbox"],
			"permit" => $_POST["permit"],
			"callerid" => $_POST["callerid"],
			"callcounter" => $_POST["callcounter"],
			"faxdetect" => $_POST["faxdetect"],
			"account" => $_POST["account"]
		);
		$ext = new Extension($dict, "update");
		$stmt1 = $this->db->exec($ext->update_sip_sqlscript());
		$stmt2 = $this->db->exec($ext->update_users_sqlscript());
		$this->apply_config();
		header('Content-Type: application/json');
		echo '{"status": "UPDATE OK", "code": 200}';
	}
	public function delete_sip_extension(){
		$this->_get_db_connection("asterisk");
		$dict = array("account" => $_POST["account"]);
		$ext = new Extension($dict, "delete");
		$stmt1 = $this->db->exec($ext->delete_sip_sqlscript());
		$stmt2 = $this->db->exec($ext->delete_users_sqlscript());
		$stmt3 = $this->db->exec($ext->delete_devices_sqlscript());
		$this->apply_config();
		header('Content-Type: application/json');
		echo '{"status": "DELETE OK", "code": 200}';
	}
	private function apply_retrieve(){
		exec("/var/lib/asterisk/bin/retrieve_conf", $data);
	}
	private function show_ampuser($dict){
		exec('/usr/sbin/asterisk -rx "database show AMPUSER '.$dict["grpnum"].'/followme');
	}
	private function put_ampuser($dict){
		exec('/usr/sbin/asterisk -rx "database put AMPUSER '.$dict["grpnum"].'/followme/changecid default"');
		exec('/usr/sbin/asterisk -rx "database put AMPUSER '.$dict["grpnum"].'/followme/ddial DIRECT"');
		exec('/usr/sbin/asterisk -rx "database put AMPUSER '.$dict["grpnum"].'/followme/fixedcid "');
		exec('/usr/sbin/asterisk -rx "database put AMPUSER '.$dict["grpnum"].'/followme/grpconf ENABLED"');
		exec('/usr/sbin/asterisk -rx "database put AMPUSER '.$dict["grpnum"].'/followme/grplist '.$dict["grplist"].'"');
		exec('/usr/sbin/asterisk -rx "database put AMPUSER '.$dict["grpnum"].'/followme/grptime '.$dict["grptime"].'"');
		exec('/usr/sbin/asterisk -rx "database put AMPUSER '.$dict["grpnum"].'/followme/prering '.$dict["pre_ring"].'"');
	}
	private function deltree_ampuser($dict){
		exec('/usr/sbin/asterisk -rx "database deltree AMPUSER '.$dict["grpnum"].'/followme"');
	}
	public function add_followme_extension(){
		$this->_get_db_connection("asterisk");
		$dict = array(
			"grpnum" => $_POST["grpnum"],
			"strategy" => $_POST["strategy"],
			"grptime" => $_POST["grptime"],
			"grppre" => $_POST["grppre"],
			"grplist" => $_POST["grplist"],
			"annmsg_id" => $_POST["annmsg_id"],
			"postdest" => $_POST["postdest"],
			"dring" => $_POST["dring"],
			"remotealert_id" => $_POST["remotealert_id"],
			"needsconf" => $_POST["needsconf"],
			"toolate_id" => $_POST["toolate_id"],
			"pre_ring" => $_POST["pre_ring"],
			"ringing" => $_POST["ringing"]
		);
		$this->put_ampuser($dict);
		$find = new FindMeFollow($dict, "insert");
		$stmt0 = $this->db->prepare($find->select_findmefollow_sqlscript());
		$stmt0->execute();
		$row = $stmt0->fetch(PDO::FETCH_ASSOC);
		if(!$row){
			$stmt1 = $this->db->exec($find->insert_into_findmefollow_sqlscript());
			$this->apply_retrieve();
			$this->apply_config();
		}
		header('Content-Type: application/json');
		echo '{"status": "INSERT OK", "code": 200}';
	}
	public function update_followme_extension(){
		$this->_get_db_connection("asterisk");
		$dict = array(
			"grpnum" => $_POST["grpnum"],
			"strategy" => $_POST["strategy"],
			"grptime" => $_POST["grptime"],
			"grppre" => $_POST["grppre"],
			"grplist" => $_POST["grplist"],
			"annmsg_id" => $_POST["annmsg_id"],
			"postdest" => $_POST["postdest"],
			"dring" => $_POST["dring"],
			"remotealert_id" => $_POST["remotealert_id"],
			"needsconf" => $_POST["needsconf"],
			"toolate_id" => $_POST["toolate_id"],
			"pre_ring" => $_POST["pre_ring"],
			"ringing" => $_POST["ringing"]
		);
		$this->put_ampuser($dict);
		$find = new FindMeFollow($dict, "update");
		$stmt1 = $this->db->exec($find->update_findmefollow_sqlscript());
		$this->apply_retrieve();
		$this->apply_config();
		header('Content-Type: application/json');
		echo '{"status": "UPDATE OK", "code": 200}';
	}
	public function delete_followme_extension(){
		$this->_get_db_connection("asterisk");
		$dict = array("grpnum" => $_POST["grpnum"]);
		$this->deltree_ampuser($dict);
		$find = new FindMeFollow($dict, "delete");
		$stmt1 = $this->db->exec($find->delete_findmefollow_sqlscript());
		$this->apply_retrieve();
		$this->apply_config();
		header('Content-Type: application/json');
		echo '{"status": "DELETE OK", "code": 200}';
	}
	public function view_followme_extension(){
		$this->_get_db_connection("asterisk");
		$dict = array("grpnum" => $_POST["grpnum"]);
		$find = new FindMeFollow($dict, "select");
		$stmt1 = $this->db->prepare($find->select_findmefollow_sqlscript());
		$stmt1->execute();
		$result = (array)$stmt1->fetchAll(PDO::FETCH_ASSOC);
		header('Content-Type: application/json');
		echo json_encode($result);
	}
	public function view_followme_all_extensions(){
		$this->_get_db_connection("asterisk");
		$dict = array();
		$find = new FindMeFollow($dict, "selectall");
		$stmt1 = $this->db->prepare($find->select_all_findmefollow_sqlscript());
		$stmt1->execute();
		$result = (array)$stmt1->fetchAll(PDO::FETCH_ASSOC);
		header('Content-Type: application/json');
		echo json_encode($result);
	}
}
?>
