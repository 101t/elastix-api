# Elastix API

## Table Of Contents
* [Introduction](#introduction)
    * [Download and Installation](#download-and-installation)
    * [Base URL](#base-url)
* [API Implementation](#api-implementation)
    * [Get Authentication](#get-authentication)
    * [Get SIP Peers](#get-sip-peers)
    * [Get SIP Extensions](#get-sip-extensions)
    * [Check System Resources](#check-system-resources)
    * [Get CDR Report](#get-cdr-report)
    * [Get \*.wav files](#get-wav-files)
    * [Get Hard Driver State](#get-hard-driver-state)
    * [Check IPTable Status](#check-iptable-status)
    * [Get Active Call (Live Calls)](#get-active-call-live-calls)
    * [SIP Trunk / Extension Management (Create, Read, Update, Delete)](#sip-trunk--extension-management-create-read-update-delete)
    * [Follow Me Extension Management (Create, Read, Update, Delete)](#follow-me-extension-management-create-read-update-delete)

# Introduction

Welcome to elastix-api repository, it is API provider will be included inside Elastix Server, it is written in **PHP4 no dependency required** because it is based on Elastix available dependency, some external libraries included such as json_encode and parse_ini because php4 version didn't provide it, this project cover some important functions of Elastix to build your own CRM system, or to take control on your PBX using external dashboard, it may also to control many PBX systems you have installed on your Data Center, and more.

This contains two main classes:
1. Asterisk (Telephony Framework).
2. Elastix (FreePBX, Web Control Panel of Asterisk).

## Download and Installation:
To download and install this repository in your Elastix server you need to `connect as ssh` then go to folder `/var/www/html`, then you can write:
```shell
root@elastix# git clone https://github.com/tarek-aec/elastix-api.git
```
After download you need to rename folder:
```
root@elastix# mv elastix-api/ api/
```
Go to inside renamed folder `api/` and open `api.php` to set token key:
```shell
root@elastix# nano /var/www/html/api/api.php
```
Inside file replace any 50 chars key generated in `$key = "YOUR_SECRETKEY_50_RANDOM_CHARS";`:
```php
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

...

}
```
## Base URL:
Project URL will be located in server-side under the folder `/var/www/html` beside elastix php project, the base URL will be based on your IP server + `/api/api.php`, remember that Elastix web project has self-signed SSL certificate this may cause verification error in your implementation code.
```
https://<your_server_ip>:443/api/api.php
```

# API Implementation:
In this API tutorial we are using request **GET/POST/HEAD** methods to manipulate with Asterisk/Elastix using many methods `elastix nd asterisk commands`, also we connect with MySQL database to make some modification, mostly we receive JSON response as data content type. 

[Let's generate](https://www.lastpass.com/password-generator) any 50 random chars and numbers for the key to be used in `Authorization` this will be passed in HTTP Header as the key token to be authorized to connect to this API, in this case let's be `kJBJzUqZ2W9THvW9vrFTEzXN7NNfDv9XyzENAn7teWDsZCcYHj`, then HTTP Header will be like:

```
POST /api/api.php HTTP/1.1
Content-Type: application/x-www-form-urlencoded
Authorization: kJBJzUqZ2W9THvW9vrFTEzXN7NNfDv9XyzENAn7teWDsZCcYHj
```
This project covers important functions:

* Get Authentication
* Get SIP Peers
* Get SIP Extensions
* Check System Resources
* Get CDR Report
* Get \*.wav files
* Get Hard Driver State
* Check IPTable Status
* Get Active Call (Live Calls)
* SIP Trunk / Extension Management (Create, Read, Update, Delete)
* Follow Me Extension Management (Create, Read, Update, Delete)


## Get Authentication:
**HTTP Method:** POST<br>
**Authorization Head:** required<br>
**URL:** https://<your_server_ip>:443/api/api.php?cmd=auth<br>
**Content-Type:** application/x-www-form-urlencoded<br>
**Response:** application/json
```json
{
    "lordword": "YWRtaW46MTIzNDU2"
}
```
This response is username & password encoded as base64 after decode will get username:password of Elastix Server in this case is 'admin:123456'.

## Get SIP Peers:
**HTTP Method:** POST<br>
**Authorization Head:** required<br>
**URL:** https://<your_server_ip>:443/api/api.php?cmd=sippeers<br>
**Content-Type:** application/x-www-form-urlencoded<br>
**Response:** application/json

The response will contain all status information about extensions and siptrunk of PBX, this function is useful to check if online/offline for internal IP Phone devices.
```json
[{
    "Event": "PeerEntry",
    "Channeltype": "SIP",
    "ObjectName": "100",
    "ChanObjectType": "peer",
    "IPaddress": "1.1.1.1",
    "IPport": "1025",
    "Dynamic": "yes",
    "AutoForcerport": "no",
    "Forcerport": "no",
    "AutoComedia": "no",
    "Comedia": "no",
    "VideoSupport": "no",
    "TextSupport": "no",
    "ACL": "yes",
    "Status": "OK (89 ms)",
    "RealtimeDevice": "no",
    "Description": ""
}, {
    "Event": "PeerEntry",
    "Channeltype": "SIP",
    "ObjectName": "101",
    "ChanObjectType": "peer",
    "IPaddress": "1.1.1.1",
    "IPport": "5062",
    "Dynamic": "yes",
    "AutoForcerport": "no",
    "Forcerport": "no",
    "AutoComedia": "no",
    "Comedia": "no",
    "VideoSupport": "no",
    "TextSupport": "no",
    "ACL": "yes",
    "Status": "OK (241 ms)",
    "RealtimeDevice": "no",
    "Description": ""
}, {
    "Event": "PeerEntry",
    "Channeltype": "SIP",
    "ObjectName": "900000000000",
    "ChanObjectType": "peer",
    "IPaddress": "10.10.10.10",
    "IPport": "5060",
    "Dynamic": "no",
    "AutoForcerport": "no",
    "Forcerport": "no",
    "AutoComedia": "no",
    "Comedia": "no",
    "VideoSupport": "no",
    "TextSupport": "no",
    "ACL": "no",
    "Status": "OK (5 ms)",
    "RealtimeDevice": "no",
    "Description": ""
}]
```

## Get SIP Extensions
**HTTP Method:** POST<br>
**Authorization Head:** required<br>
**URL:** https://<your_server_ip>:443/api/api.php?cmd=sipextensions<br>
**Content-Type:** application/x-www-form-urlencoded<br>
**Response:** application/json

The response will contain SIP Extensions profile informations including users name username and password for every connected device in PBX, it is useful to check profile info.
```json
{
    "100": {
        "deny": "0.0.0.0\/0.0.0.0",
        "secret": "strongpassword",
        "dtmfmode": "rfc2833",
        "canreinvite": "no",
        "context": "from-internal",
        "host": "dynamic",
        "trustrpid": "yes",
        "sendrpid": "no",
        "type": "friend",
        "nat": "no",
        "port": "5060",
        "qualify": "yes",
        "qualifyfreq": "60",
        "transport": "udp",
        "avpf": "no",
        "icesupport": "no",
        "encryption": "no",
        "callgroup": "",
        "pickupgroup": "",
        "dial": "SIP\/100",
        "mailbox": "100@device",
        "permit": "0.0.0.0\/0.0.0.0",
        "callerid": "Lowis <100>",
        "callcounter": "yes",
        "faxdetect": "no"
    },
    "101": {
        "deny": "0.0.0.0\/0.0.0.0",
        "secret": "strongpassword",
        "dtmfmode": "rfc2833",
        "canreinvite": "no",
        "context": "from-internal",
        "host": "dynamic",
        "trustrpid": "yes",
        "sendrpid": "no",
        "type": "friend",
        "nat": "no",
        "port": "5060",
        "qualify": "yes",
        "qualifyfreq": "60",
        "transport": "udp",
        "avpf": "no",
        "icesupport": "no",
        "encryption": "no",
        "callgroup": "",
        "pickupgroup": "",
        "dial": "SIP\/101",
        "mailbox": "101@device",
        "permit": "0.0.0.0\/0.0.0.0",
        "callerid": "John <101>",
        "callcounter": "yes",
        "faxdetect": "no"
    },
    "900000000000": {
        "disallow": "all",
        "username": "900000000000",
        "type": "peer",
        "qualify": "yes",
        "secret": "strongpassword",
        "nat": "auto",
        "insecure": "port,invite",
        "host": "10.10.10.10",
        "fromuser": "900000000000",
        "fromdomain": "10.10.10.10",
        "dtmfmode": "rfc2833",
        "allow": "alaw",
        "context": "from-trunk-sip-900000000000"
    }
}
```

## Check System Resources
**HTTP Method:** POST<br>
**Authorization Head:** required<br>
**URL:** https://<your_server_ip>:443/api/api.php?cmd=systemresources<br>
**Content-Type:** application/x-www-form-urlencoded<br>
**Response:** application/json

Actually it is not related to PBX but it is related to check system live
```json
{"datetime":"11:55:42 up  3:25","users":"5","usage":"0.19, 0.19, 0.25"}
```

## Get CDR Report
**HTTP Method:** POST<br>
**Authorization Head:** required<br>
**URL:** https://<your_server_ip>:443/api/api.php?cmd=cdrreport<br>
**Content-Type:** application/x-www-form-urlencoded<br>
**Response:** application/json

I think it is the most important part of this repo is CDR report to get all call history that stored in MySQL in Asterisk database including the internal and external calls, and also call state (ALL, ANSWERED, BUSY, FAILED, NO ANSWER)
When implementing this function no parameter is required but this query could be customized with short filter parameters to get CDR report by range date or by call state, the request parameters:
```
start_date 	: 	2017-12-05 00:00:00
end_date 	: 	2017-12-07 23:59:59
field_name 	: 	src, dst, channel, dstchannel, accountcode
field_pattern    : 	<any>
status 		: 	ALL, ANSWERED, BUSY, FAILED, NO ANSWER
limit 		: 	100 <integer>
recordings 	: 	all, only, without
```
The response example will be:
```json
[{
    "calldate": "2015-12-05 10:07:05",
    "clid": "\"02164440375\" <02164440375>",
    "src": "02164440375",
    "dst": "s",
    "dcontext": "ivr-4",
    "channel": "SIP\/902422277777-00000031",
    "dstchannel": "",
    "lastapp": "WaitExten",
    "lastdata": "10,",
    "duration": "41",
    "billsec": "41",
    "disposition": "ANSWERED",
    "amaflags": "3",
    "accountcode": "",
    "uniqueid": "1449302825.65",
    "userfield": "",
    "recordingfile": "",
    "cnum": "",
    "cnam": "",
    "outbound_cnum": "",
    "outbound_cnam": "",
    "dst_cnam": "",
    "did": "902422277777"
}, {
    "calldate": "2015-12-05 15:26:49",
    "clid": "\"05396666666\" <05396666666>",
    "src": "05396666666",
    "dst": "101",
    "dcontext": "from-did-direct",
    "channel": "SIP\/902422277777-00000032",
    "dstchannel": "SIP\/101-00000033",
    "lastapp": "Dial",
    "lastdata": "SIP\/101,,tr",
    "duration": "53",
    "billsec": "26",
    "disposition": "ANSWERED",
    "amaflags": "3",
    "accountcode": "",
    "uniqueid": "1449322009.66",
    "userfield": "",
    "recordingfile": "exten-101-05396666666-20151205-152659-1449322009.66.wav",
    "cnum": "05396666666",
    "cnam": "05396666666",
    "outbound_cnum": "",
    "outbound_cnam": "",
    "dst_cnam": "",
    "did": "902422277777"
}, {
    "calldate": "2015-12-05 19:57:13",
    "clid": "\"05075478515\" <05075478515>",
    "src": "05075478515",
    "dst": "s",
    "dcontext": "ivr-4",
    "channel": "SIP\/902422277777-00000034",
    "dstchannel": "",
    "lastapp": "BackGround",
    "lastdata": "custom\/giris_santral",
    "duration": "7",
    "billsec": "7",
    "disposition": "ANSWERED",
    "amaflags": "3",
    "accountcode": "",
    "uniqueid": "1449338233.68",
    "userfield": "",
    "recordingfile": "",
    "cnum": "",
    "cnam": "",
    "outbound_cnum": "",
    "outbound_cnam": "",
    "dst_cnam": "",
    "did": "902422277777"
}, {
    "calldate": "2015-12-07 14:18:20",
    "clid": "\"05512444444\" <05512444444>",
    "src": "05512444444",
    "dst": "100",
    "dcontext": "from-internal",
    "channel": "Local\/100@from-queue-00000008;2",
    "dstchannel": "SIP\/100-00000036",
    "lastapp": "Dial",
    "lastdata": "SIP\/100,,trM(auto-blkvm)",
    "duration": "10",
    "billsec": "0",
    "disposition": "NO ANSWER",
    "amaflags": "3",
    "accountcode": "",
    "uniqueid": "1449490700.71",
    "userfield": "",
    "recordingfile": "",
    "cnum": "05512444444",
    "cnam": "05512444444",
    "outbound_cnum": "",
    "outbound_cnam": "",
    "dst_cnam": "",
    "did": ""
}]
```

## Get *.wav files
**HTTP Method:** POST<br>
**Authorization Head:** required<br>
**URL:** https://<your_server_ip>:443/api/api.php?cmd=getwavfile&name=THE_FILE_NAME<br>
**Content-Type:** application/x-www-form-urlencoded<br>
**Response:** application/octet-stream

This function let you download wave file from PBX central to listen to your client, this is helpful for helpdesk services and let you learn more about customer what customer needs from help desk to give good and useful information ... etc. in this case this requred two paramter based on CDR Report json response that include two important fields **wavname** and **calldate** this two fields will combinate it in one param called **name** example:

```
wavname      : "out-05333333333-101-20151208-102449-1449563089.106.wav"
calldate     : "2015/12/08"
```
The `name` will be like that:
```
name         : "/2015/12/08/out-05333333333-101-20151208-102449-1449563089.106.wav"
```
name parameter represents filename director that stored in Elastix Central under main director `/var/spool/asterisk/monitor`, The response if file found as:
```
Content-Disposition: attachment; filename=out-05333333333-101-20151208-102449-1449563089.106.wav
Content-Length: 4029223
Content-Type: application/octet-stream;
```
If file does not exists the response will be json as
```
HTTP/1.0 404 Not Found
Content-Type: application/json

{"status": "File not found", "code": 404}

```

## Get Hard Driver State 
**HTTP Method:** POST<br>
**Authorization Head:** required<br>
**URL:** https://<your_server_ip>:443/api/api.php?cmd=getharddrivers<br>
**Content-Type:** application/x-www-form-urlencoded<br>
**Response:** application/json

This function built to check hard disk state, size, available space, used space, mount, and also some additional information about specific directories in Elastix Server that may could be filled quickly such as voicemails, logs, recording, this may fill elastix server's hard drive we should be careful to know:
```
logs           : /var/log
thirdparty     : /opt
voicemails     : /var/spool/asterisk/voicemail
backup         : /var/www/backup
configuration  : /etc
recording      : /var/spool/asterisk/monitor
```
The JSON response will be:
```json
{
	"size": "SIZE_STRING",
	"used": "SIZE_STRING",
	"avail": "SIZE_STRING",
	"usepercent": "SIZE_STRING",
	"mount": "SIZE_STRING",
	"harddisk": "SIZE_STRING",
	"logs": "SIZE_STRING",
	"thirdparty": "SIZE_STRING",
	"voicemails": "SIZE_STRING",
	"backups": "SIZE_STRING",
	"configuration": "SIZE_STRING",
	"recording": "SIZE_STRING"
}
```
## Check IPTable Status
**HTTP Method:** POST<br>
**Authorization Head:** required<br>
**URL:** https://<your_server_ip>:443/api/api.php?cmd=getiptablesstatus<br>
**Content-Type:** application/x-www-form-urlencoded<br>
**Response:** application/json

This function important to check iptable state in Elastix Server, in `pid` you will see string result of iptable status. `is_exist` means firewall is running if true else false.
```json
{"pid": "IPTABLE_SCREENSHOT", "is_exist": "true"}
```

## Get Active Call (Live Calls)
**HTTP Method:** POST<br>
**Authorization Head:** required<br>
**URL:** https://<your_server_ip>:443/api/api.php?cmd=activecall<br>
**Content-Type:** application/x-www-form-urlencoded<br>
**Response:** application/json

This function is awesome to check current live calls, Asterisk does not give you this feature parsed as json, the command is `/usr/sbin/asterisk -rx "core show channels verbose"` the normal output in terminal:

```shell
Channel              Location             State   Application(Data)             
Local/2002@from-queu s@macro-dial-one:43  Up      Dial(SIP/2002,15,trM(auto-blkv
Local/2002@from-queu 0001@from-queue:1    Up      AppQueue((Outgoing Line))     
SIP/5000-00001186    s@macro-dialout-trun Ring    Dial(SIP/908500000000/05346782
SIP/2007-00001185    s@macro-dial-one:1   Up      AppDial((Outgoing Line))      
SIP/902422277777-000 0001@ext-queues:40   Up      Queue(0001,t,,,,,,,,)         
SIP/908500000000-000 05346782695@from-tru Down    AppDial((Outgoing Line))      
6 active channels
3 active calls
12235 calls processed
```
The response example as json:
```json
[{
    "Channel": "Local\/2003@from-queu",
    "Context": "from-queue",
    "Extension": "0001",
    "Prio": "1",
    "State": "Ringing",
    "Application": "AppQueue",
    "Data": "(Outgoing Line)",
    "CallerID": "2003",
    "Duration": "00:00:04",
    "Accountcode": "",
    "PeerAccount": "",
    "BridgedTo": "(None)"
}, {
    "Channel": "Local\/2003@from-queu",
    "Context": "macro-dial-one",
    "Extension": "s",
    "Prio": "43",
    "State": "Ring",
    "Application": "Dial",
    "Data": "SIP\/2003,,trM(auto-blkvm)",
    "CallerID": "02166311616",
    "Duration": "00:00:04",
    "Accountcode": "",
    "PeerAccount": "",
    "BridgedTo": "(None)"
}, {
    "Channel": "SIP\/2003-0000115b",
    "Context": "from-internal",
    "Extension": "2003",
    "Prio": "1",
    "State": "Ringing",
    "Application": "AppDial",
    "Data": "(Outgoing Line)",
    "CallerID": "2003",
    "Duration": "00:00:04",
    "Accountcode": "",
    "PeerAccount": "",
    "BridgedTo": "(None)"
}, {
    "Channel": "SIP\/902422277777-000",
    "Context": "ext-queues",
    "Extension": "0001",
    "Prio": "40",
    "State": "Up",
    "Application": "Queue",
    "Data": "0001,t,,,,,,,,",
    "CallerID": "02166311616",
    "Duration": "00:00:53",
    "Accountcode": "",
    "PeerAccount": "",
    "BridgedTo": "(None)"
}, {
    "Channel": "SIP\/902422277777-000",
    "Context": "ext-queues",
    "Extension": "0001",
    "Prio": "40",
    "State": "Up",
    "Application": "Queue",
    "Data": "0001,t,,,,,,,,",
    "CallerID": "05559991111",
    "Duration": "00:02:19",
    "Accountcode": "",
    "PeerAccount": "",
    "BridgedTo": "Local\/200"
}, {
    "Channel": "Local\/2008@from-queu",
    "Context": "from-queue",
    "Extension": "0001",
    "Prio": "1",
    "State": "Up",
    "Application": "AppQueue",
    "Data": "(Outgoing Line)",
    "CallerID": "2008",
    "Duration": "00:01:13",
    "Accountcode": "",
    "PeerAccount": "",
    "BridgedTo": "SIP\/90242"
}, {
    "Channel": "Local\/2008@from-queu",
    "Context": "macro-dial-one",
    "Extension": "s",
    "Prio": "43",
    "State": "Up",
    "Application": "Dial",
    "Data": "SIP\/2008,,trM(auto-blkvm)",
    "CallerID": "05559991111",
    "Duration": "00:01:13",
    "Accountcode": "",
    "PeerAccount": "",
    "BridgedTo": "SIP\/2008-"
}, {
    "Channel": "SIP\/2008-00001152",
    "Context": "macro-dial-one",
    "Extension": "s",
    "Prio": "1",
    "State": "Up",
    "Application": "AppDial",
    "Data": "(Outgoing Line)",
    "CallerID": "2008",
    "Duration": "00:01:13",
    "Accountcode": "",
    "PeerAccount": "",
    "BridgedTo": "Local\/200"
}, "8 active channels", "4 active calls", "12164 calls processed"]
```

## SIP Trunk / Extension Management (Create, Update, Delete, Read)
**HTTP Method:** POST<br>
**Authorization Head:** required<br>
**URL:** https://<your_server_ip>:443/api/api.php?cmd=addextension<br>
**Content-Type:** application/x-www-form-urlencoded<br>
**Response:** application/json

The `cmd` here will be changable for insertion `cmd=addextension`, updating `cmd=updateextension` and deletion `cmd=deleteextension`.
This function has required parameters for **insertion** and **updating**:
```
name		: "100"
deny		: "0.0.0.0\/0.0.0.0"
secret		: "strongpassword"
dtmfmode	: "rfc2833"
canreinvite	: "no"
context		: "from-internal"
host		: "dynamic"
trustrpid	: "yes"
sendrpid	: "no"
type		: "friend"
nat		    : "no"
port		: "5060"
qualify		: "yes"
qualifyfreq	: "60"
transport	: "udp"
avpf		: "no"
icesupport	: "no"
encryption	: "no"
callgroup	: ""
pickupgroup	: ""
dial		: "SIP\/104"
mailbox		: "104@device"
permit		: "0.0.0.0\/0.0.0.0"
callerid	: "John <104>"
callcounter	: "yes"
faxdetect	: "no"
account         : "100"
```
for **deletion** only one parameter required:
```
account         : "100"
```
The response will be:
```shell
#For insetion
{"status": "INSERT OK", "code": 200}
#For updating
{"status": "UPDATE OK", "code": 200}
#For deletion
{"status": "DELETE OK", "code": 200}
```

## Follow Me Extension Management (Create, Update, Delete, Read)
**HTTP Method:** POST<br>
**Authorization Head:** required<br>
**URL:** https://<your_server_ip>:443/api/api.php?cmd=addfollowmeextension<br>
**Content-Type:** application/x-www-form-urlencoded<br>
**Response:** application/json

The `cmd` here will be changable for insertion `cmd=addfollowmeextension`, updating `cmd=updatefollowmeextension` and deletion `cmd=deletefollowmeextension` get followme for one extension `cmd=getfollowmeextension`, get followme for all extensions `cmd=getallfollowmeextensions`.

This function has required parameters for **insertion** and **updating**:
```
grpnum			: "100" <username>
strategy		: "ringallv2" <Options: ringallv2, ringallv2-prim, ringall, ringall-prim, hunt, hunt-prim, memoryhunt, memoryhunt-prim, firstavailable, firstnotonphone>
grptime			: "20" <Options: 5, 10, 15, 20, 25, 30, 45, 60, maximum 60 seconds>
grppre			: ""
grplist			: "101, 105, 90539999999#, 908509999999#" <Follow Me List, every number seperated by,>
annmsg_id		: "0"
postdest		: ""
dring			: ""
remotealert_id	        : "0"
needsconf		: ""
toolate_id		: "0"
pre_ring		: "0" <Initial Ring Time from 0 to 60 seconds>
ringing			: "Ring" <Play Music On Hold, Options: Ring, default, none>
```
for **deletion** and **followme for one extension** only one parameter required:
```
grpnum                  : "100" <username>
```
to get **followme for all extensions** no extra parameter required.