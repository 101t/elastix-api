<?php 
/*
All Params:
deny, secret, dtmfmode, canreinvite, context, host, trustrpid, sendrpid, type, nat, port, qualify, qualifyfreq, transport, avpf, icesupport, encryption, callgroup, pickupgroup, dial, mailbox, permit, callerid, callcounter, faxdetect
Additional Params:
accountcode, account
*/
class Extension{
	public function __construct($dict, $flag)
	{
		if ($flag === "insert" || $flag == "update") {
			$this->name = $dict["name"];
			$this->deny = $dict["deny"];
			$this->secret = $dict["secret"];
			$this->dtmfmode = $dict["dtmfmode"];
			$this->canreinvite = $dict["canreinvite"];
			$this->context = $dict["context"];
			$this->host = $dict["host"];
			$this->trustrpid = $dict["trustrpid"];
			$this->sendrpid = $dict["sendrpid"];
			$this->type = $dict["type"];
			$this->nat = $dict["nat"];
			$this->port = $dict["port"];
			$this->qualify = $dict["qualify"];
			$this->qualifyfreq = $dict["qualifyfreq"];
			$this->transport = $dict["transport"];
			$this->avpf = $dict["avpf"];
			$this->icesupport = $dict["icesupport"];
			$this->encryption = $dict["encryption"];
			$this->callgroup = $dict["callgroup"];
			$this->pickupgroup = $dict["pickupgroup"];
			$this->dial = $dict["dial"];
			$this->mailbox = $dict["mailbox"];
			$this->permit = $dict["permit"];
			$this->callerid = $dict["callerid"];
			$this->callcounter = $dict["callcounter"];
			$this->faxdetect = $dict["faxdetect"];
			$this->accountcode = "";
			$this->account = $dict["account"];
		} else if ($flag === "delete"){
			$this->account = $dict["account"];
		}
	}
	public function select_sip_sqlscript(){
		$sql_script = "SELECT * FROM sip WHERE id = '".$this->account."'";
		return $sql_script;
	}
	public function insert_into_sip_sqlscript(){
		$sql_script = "INSERT IGNORE INTO sip (id, keyword, data, flags) VALUES ".
			"('".$this->account."', 'deny', '".$this->deny."', 2),".
			"('".$this->account."', 'secret', '".$this->secret."', 3),".
			"('".$this->account."', 'dtmfmode', '".$this->dtmfmode."', 4),".
			"('".$this->account."', 'canreinvite', '".$this->canreinvite."', 5),".
			"('".$this->account."', 'context', '".$this->context."', 6),".
			"('".$this->account."', 'host', '".$this->host."', 7),".
			"('".$this->account."', 'trustrpid', '".$this->trustrpid."', 8),".
			"('".$this->account."', 'sendrpid', '".$this->sendrpid."', 9),".
			"('".$this->account."', 'type', '".$this->type."', 10),".
			"('".$this->account."', 'nat', '".$this->nat."', 11),".
			"('".$this->account."', 'port', '".$this->port."', 12),".
			"('".$this->account."', 'qualify', '".$this->qualify."', 13),".
			"('".$this->account."', 'qualifyfreq', '".$this->qualifyfreq."', 14),".
			"('".$this->account."', 'transport', '".$this->transport."', 15),".
			"('".$this->account."', 'avpf', '".$this->avpf."', 16),".
			"('".$this->account."', 'icesupport', '".$this->icesupport."', 17),".
			"('".$this->account."', 'encryption', '".$this->encryption."', 18),".
			"('".$this->account."', 'callgroup', '".$this->callgroup."', 19),".
			"('".$this->account."', 'pickupgroup', '".$this->pickupgroup."', 20),".
			"('".$this->account."', 'dial', '".$this->dial."', 21),".
			"('".$this->account."', 'mailbox', '".$this->mailbox."', 22),".
			"('".$this->account."', 'permit', '".$this->permit."', 23),".
			"('".$this->account."', 'callerid', '".$this->callerid."', 24),".
			"('".$this->account."', 'callcounter', '".$this->callcounter."', 25),".
			"('".$this->account."', 'faxdetect', '".$this->faxdetect."', 26),".
			"('".$this->account."', 'accountcode', '".$this->accountcode."', 27),".
			"('".$this->account."', 'account', '".$this->account."', 28)";
		return $sql_script;
	}
	public function update_sip_sqlscript(){
		$sql_script = "INSERT INTO sip (id, keyword, data, flags) VALUES ".
		"('".$this->account."', 'deny', '".$this->deny."', 2),".
		"('".$this->account."', 'secret', '".$this->secret."', 3),".
		"('".$this->account."', 'dtmfmode', '".$this->dtmfmode."', 4),".
		"('".$this->account."', 'canreinvite', '".$this->canreinvite."', 5),".
		"('".$this->account."', 'context', '".$this->context."', 6),".
		"('".$this->account."', 'host', '".$this->host."', 7),".
		"('".$this->account."', 'trustrpid', '".$this->trustrpid."', 8),".
		"('".$this->account."', 'sendrpid', '".$this->sendrpid."', 9),".
		"('".$this->account."', 'type', '".$this->type."', 10),".
		"('".$this->account."', 'nat', '".$this->nat."', 11),".
		"('".$this->account."', 'port', '".$this->port."', 12),".
		"('".$this->account."', 'qualify', '".$this->qualify."', 13),".
		"('".$this->account."', 'qualifyfreq', '".$this->qualifyfreq."', 14),".
		"('".$this->account."', 'transport', '".$this->transport."', 15),".
		"('".$this->account."', 'avpf', '".$this->avpf."', 16),".
		"('".$this->account."', 'icesupport', '".$this->icesupport."', 17),".
		"('".$this->account."', 'encryption', '".$this->encryption."', 18),".
		"('".$this->account."', 'callgroup', '".$this->callgroup."', 19),".
		"('".$this->account."', 'pickupgroup', '".$this->pickupgroup."', 20),".
		"('".$this->account."', 'dial', '".$this->dial."', 21),".
		"('".$this->account."', 'mailbox', '".$this->mailbox."', 22),".
		"('".$this->account."', 'permit', '".$this->permit."', 23),".
		"('".$this->account."', 'callerid', '".$this->callerid."', 24),".
		"('".$this->account."', 'callcounter', '".$this->callcounter."', 25),".
		"('".$this->account."', 'faxdetect', '".$this->faxdetect."', 26),".
		"('".$this->account."', 'accountcode', '".$this->accountcode."', 27),".
		"('".$this->account."', 'account', '".$this->account."', 28)".
		" ON DUPLICATE KEY UPDATE id=VALUES(id), keyword=VALUES(keyword) , data=VALUES(data), flags=VALUES(flags)"; 
		// 
		return $sql_script;
	}
	public function delete_sip_sqlscript(){
		$sql_script = "DELETE FROM sip WHERE id='".$this->account."'";
		return $sql_script;
	}
	/*
		extension, password, name, voicemail, ringtimer, noanswer, recording, outboundcid, sipname, mohclass, noanswer_cid, busy_cid, chanunavail_cid, noanswer_dest, busy_dest, chanunavail_dest
	*/
	public function insert_into_users_sqlscript(){
		$sql_script = "INSERT IGNORE INTO users (extension, password, name, voicemail, ringtimer, noanswer, recording, outboundcid, sipname, mohclass, noanswer_cid, busy_cid, chanunavail_cid, noanswer_dest, busy_dest, chanunavail_dest) VALUES ".
			"('".$this->account."', '', '".$this->name."', 'novm', 0, '', '', '', '".$this->account."', 'default', '', '', '', '', '', '')";
		return $sql_script;
	}
	public function update_users_sqlscript(){
		$sql_script = "UPDATE users SET extension='".$this->account."', name='".$this->name."', sipname='".$this->account."' WHERE extension = '".$this->account."'";
		return $sql_script;
	}
	public function delete_users_sqlscript(){
		$sql_script = "DELETE FROM users WHERE extension = '".$this->account."'";
		return $sql_script;	
	}

	public function insert_into_devices_sqlscript(){
		$sql_script = "INSERT IGNORE INTO devices (id, tech, dial, devicetype, user, description, emergency_cid) VALUES ('".$this->account."', 'sip', '".$this->dial."', 'fixed', '".$this->account."', '".$this->account."', '')";
		return $sql_script;	
	}
	public function delete_devices_sqlscript(){
		$sql_script = "DELETE FROM devices WHERE id = '".$this->account."'";
		return $sql_script;	
	}
}

class FindMeFollow
{	
	/*
		grpnum, strategy, grptime, grppre, grplist, annmsg_id, postdest, dring, remotealert_id, needsconf, toolate_id, pre_ring, ringing 

		http://community.freepbx.org/t/sql-database-clears-information-when-open-freepbx-webpage/15000/5
	*/
	function __construct($dict, $flag)
	{
		if ($flag === "insert" || $flag == "update") {
			$this->grpnum 		= $dict["grpnum"];
			$this->strategy 	= $dict["strategy"];
			$this->grptime 		= $dict["grptime"];
			$this->grppre 		= $dict["grppre"];
			$this->grplist 		= $dict["grplist"];
			$this->annmsg_id 	= $dict["annmsg_id"];
			$this->postdest 	= $dict["postdest"];
			$this->dring 		= $dict["dring"];
			$this->remotealert_id = $dict["remotealert_id"];
			$this->needsconf 	= $dict["needsconf"];
			$this->toolate_id 	= $dict["toolate_id"];
			$this->pre_ring 	= $dict["pre_ring"];
			$this->ringing 		= $dict["ringing"];
		} else if ($flag === "delete" || $flag === "select"){
			$this->grpnum = $dict["grpnum"];
		}
	}

	public function insert_into_findmefollow_sqlscript(){
		$sql_script = "INSERT INTO findmefollow (grpnum, strategy, grptime, grppre, grplist, annmsg_id, postdest, dring, remotealert_id, needsconf, toolate_id, pre_ring, ringing) VALUES ".
			"('".$this->grpnum."', '".$this->strategy."', ".$this->grptime.", '".$this->grppre."', '".$this->grplist."', ".$this->annmsg_id.", '".$this->postdest."', '".$this->dring."', ".$this->remotealert_id.", '".$this->needsconf."', ".$this->toolate_id.", ".$this->pre_ring.", '".$this->ringing."')";
		return $sql_script;
	}
	public function update_findmefollow_sqlscript(){
		$sql_script = "UPDATE findmefollow SET grpnum='".$this->grpnum."', strategy='".$this->strategy."', grptime=".$this->grptime.", grppre='".$this->grppre."', grplist='".$this->grplist."', annmsg_id=".$this->annmsg_id.", postdest='".$this->postdest."', dring='".$this->dring."', remotealert_id=".$this->remotealert_id.", needsconf='".$this->needsconf."', toolate_id=".$this->toolate_id.", pre_ring=".$this->pre_ring.", ringing='".$this->ringing."'";
		return $sql_script;
	}
	public function delete_findmefollow_sqlscript(){
		$sql_script = "DELETE FROM findmefollow WHERE grpnum='".$this->grpnum."'";
		return $sql_script;
	}
	public function select_findmefollow_sqlscript(){
		$sql_script = "SELECT * FROM findmefollow WHERE grpnum='".$this->grpnum."'";
		return $sql_script;
	}
	public function select_all_findmefollow_sqlscript(){
		$sql_script = "SELECT * FROM findmefollow";
		return $sql_script;	
	}
}
?>