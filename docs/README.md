# Asterisk PBX API — Developer Reference

## Table of Contents
* [Introduction](#introduction)
* [Installation](#installation)
* [Configuration Reference](#configuration-reference)
* [Driver Architecture](#driver-architecture)
* [Authentication](#authentication)
* [API Reference](#api-reference)
  * [Get Authentication](#get-authentication)
  * [Get SIP/PJSIP Peers](#get-sippjsip-peers)
  * [Get SIP Extensions](#get-sip-extensions)
  * [Get Active Calls](#get-active-calls)
  * [Originate a Call](#originate-a-call)
  * [ARI Channels](#ari-channels-asterisk-12)
  * [Check System Resources](#check-system-resources)
  * [Get CDR Report](#get-cdr-report)
  * [Get Recording File](#get-recording-file)
  * [Get Disk Usage](#get-disk-usage)
  * [Check IPTables Status](#check-iptables-status)
  * [Extension Management (CRUD)](#extension-management-crud)
  * [Follow-Me Management (CRUD)](#follow-me-management-crud)
  * [Queue Management (FreePBX)](#queue-management-freepbx-driver)

---

## Introduction

This package provides a unified PHP HTTP API over three PBX platforms:

| Driver | Platform | Notes |
|--------|----------|-------|
| `asterisk` | Asterisk 13+ | AMI + optional ARI; PJSIP (default) or legacy chan_sip |
| `issabel` | Issabel 4+ | Community successor to Elastix; same API surface |
| `freepbx` | FreePBX 13+ | Adds queue management, `fwconsole`, PJSIP table support |

> **Elastix end-of-life notice:** Elastix reached EOL in 2016.  
> Issabel is the actively maintained fork — use `PBX_DRIVER=issabel` for Issabel servers.

---

## Installation

```bash
# Clone
git clone https://github.com/101t/elastix-api.git
cd elastix-api

# Install dependencies
composer install

# Configure
cp .env.example .env
nano .env          # set API_SECRET_KEY and PBX_DRIVER at minimum
```

Place the directory under your web root (`/var/www/html/api/`) and access it at:

```
https://<server_ip>/api/api.php?cmd=<command>
```

---

## Configuration Reference

Copy `.env.example` → `.env` and fill in your values.  
Environment variables take precedence over `.env` file values; non-empty env vars take precedence over defaults.

```bash
# Minimum required
API_SECRET_KEY=<50-random-chars>
PBX_DRIVER=asterisk   # asterisk | issabel | freepbx

# AMI connection
AMI_HOST=127.0.0.1
AMI_PORT=5038
# Leave blank to auto-read from manager.conf:
AMI_USERNAME=
AMI_SECRET=

# Modern Asterisk (13+) uses PJSIP by default
CHAN_DRIVER=pjsip     # pjsip | sip

# Optional ARI (Asterisk REST Interface, port 8088)
ARI_ENABLED=false
ARI_HOST=127.0.0.1
ARI_PORT=8088
ARI_USERNAME=ari_user
ARI_SECRET=ari_password

# Database
DB_HOST=localhost
DB_PORT=3306
DB_USERNAME=root
DB_PASSWORD=

# Issabel: auto-reads DB password from /etc/issabel.conf
ISSABEL_CONF_PATH=/etc/issabel.conf

# FreePBX: auto-reads DB credentials from /etc/freepbx.conf
FREEPBX_CONF_PATH=/etc/freepbx.conf

# Recordings
RECORDINGS_PATH=/var/spool/asterisk/monitor
```

---

## Driver Architecture

```
api.php
  └── PbxApi\Api\Router        — authenticates, routes, serialises responses
        └── PbxApi\Config\Config  — .env loader + env-var override
        └── PbxDriverInterface   — contract implemented by all drivers
              ├── AsteriskDriver  — AMI + ARI + PJSIP/SIP + MySQL
              ├── IssabelDriver   ← extends AsteriskDriver
              └── FreePBXDriver   ← extends AsteriskDriver
```

Selecting a driver:

```bash
PBX_DRIVER=freepbx    # in .env
```

or at runtime:

```bash
PBX_DRIVER=issabel php -S 0.0.0.0:8080 api.php
```

---

## Authentication

Every request must include the secret key in the `Authorization` header:

```http
GET /api/api.php?cmd=sippeers HTTP/1.1
Authorization: kJBJzUqZ2W9THvW9vrFTEzXN7NNfDv9XyzENAn7teWDsZCcYHj
```

The comparison is done with `hash_equals()` to prevent timing attacks.

---

## API Reference

### Get Authentication

**Method:** POST  
**URL:** `?cmd=auth`

Returns base64-encoded `username:password` of the AMI manager user.

```json
{"lordword": "YWRtaW46MTIzNDU2"}
```

---

### Get SIP/PJSIP Peers

**Method:** POST  
**URL:** `?cmd=sippeers`

Returns all registered peers. Uses `PJSIPShowEndpoints` for chan_pjsip or `Sippeers` for chan_sip.

```json
[
  {
    "Event": "EndpointList",
    "ObjectType": "endpoint",
    "ObjectName": "100",
    "Transport": "transport-udp",
    "Aor": "100",
    "Auths": "auth100",
    "OutboundAuths": "",
    "Contacts": "100/sip:100@192.168.1.10:5060",
    "DeviceState": "Not in use",
    "ActiveChannels": ""
  }
]
```

---

### Get SIP Extensions

**Method:** POST  
**URL:** `?cmd=sipextensions`

Returns extension profile data from the config file (`pjsip.conf` or `sip_additional.conf`).

```json
{
  "100": {
    "type": "endpoint",
    "context": "from-internal",
    "transport": "transport-udp",
    "auth": "auth100",
    "aors": "100"
  }
}
```

---

### Get Active Calls

**Method:** POST  
**URL:** `?cmd=activecall`

Returns currently active channels parsed from `core show channels verbose`.

```json
[
  {
    "Channel": "PJSIP/100-00000001",
    "Context": "from-internal",
    "Extension": "200",
    "Prio": "1",
    "State": "Up",
    "Application": "Dial",
    "Data": "PJSIP/200,,tr",
    "CallerID": "100",
    "Duration": "00:01:23",
    "Accountcode": "",
    "PeerAccount": "",
    "BridgedTo": "PJSIP/200-000"
  }
]
```

---

### Originate a Call

**Method:** POST  
**URL:** `?cmd=originatecall`  
**Body parameters:**

| Parameter | Example | Description |
|-----------|---------|-------------|
| `channel` | `PJSIP/100` | Channel to originate from |
| `extension` | `200` | Destination extension |
| `context` | `from-internal` | Dialplan context |
| `callerid` | `John <100>` | Caller ID to present |
| `timeout` | `30000` | Timeout in milliseconds |

```json
{"status": "CALL INITIATED", "code": 200}
```

---

### ARI Channels (Asterisk 12+)

**Prerequisite:** `ARI_ENABLED=true` in `.env`

**Method:** POST  
**URL:** `?cmd=arichannels`

Returns active channels via the Asterisk REST Interface (`GET /ari/channels`).

```json
[
  {
    "id": "1704067200.1",
    "name": "PJSIP/100-00000001",
    "state": "Up",
    "caller": {"name": "Alice", "number": "100"},
    "connected": {"name": "Bob", "number": "200"},
    "creationtime": "2024-01-01T10:00:00.000+0000",
    "dialplan": {"context": "from-internal", "exten": "200", "priority": 1}
  }
]
```

---

### Check System Resources

**Method:** POST  
**URL:** `?cmd=systemresources`

```json
{"datetime": "11:55:42 up  3:25", "users": "5", "usage": "0.19, 0.19, 0.25"}
```

---

### Get CDR Report

**Method:** POST  
**URL:** `?cmd=cdrreport`  
**Body parameters:**

| Parameter | Example | Description |
|-----------|---------|-------------|
| `start_date` | `2024-01-01 00:00:00` | Range start |
| `end_date` | `2024-01-31 23:59:59` | Range end |
| `field_name` | `src` | Filter field: `src`, `dst`, `channel`, `dstchannel`, `accountcode`, `clid`, `cnum`, `cnam` |
| `field_pattern` | `0212` | LIKE pattern for `field_name` |
| `status` | `ANSWERED` | `ALL` \| `ANSWERED` \| `BUSY` \| `FAILED` \| `NO ANSWER` |
| `limit` | `100` | Max rows returned |

```json
[
  {
    "calldate": "2024-01-05 10:07:05",
    "src": "100",
    "dst": "200",
    "duration": "65",
    "billsec": "60",
    "disposition": "ANSWERED",
    "recordingfile": "exten-200-100-20240105-100705.wav"
  }
]
```

---

### Get Recording File

**Method:** GET  
**URL:** `?cmd=getwavfile&name=/2024/01/05/exten-200-100-20240105-100705.wav`

Downloads a recording file from `RECORDINGS_PATH`.

```http
Content-Disposition: attachment; filename="exten-200-100-20240105-100705.wav"
Content-Length: 1234567
Content-Type: application/octet-stream
```

Not found response:

```json
{"status": "File not found", "code": 404}
```

---

### Get Disk Usage

**Method:** POST  
**URL:** `?cmd=getharddrivers`

```json
{
  "harddisk": {"size": "100G", "used": "25G", "avail": "75G", "usepercent": "25%", "mount": "/"},
  "logs": ["1.2G", "/var/log"],
  "voicemails": ["256M", "/var/spool/asterisk/voicemail"],
  "recording": ["8.5G", "/var/spool/asterisk/monitor"]
}
```

*Issabel driver* additionally returns `issabel_modules`.

---

### Check IPTables Status

**Method:** POST  
**URL:** `?cmd=getiptablesstatus`

```json
{"pid": "iptables: Firewall is not running.", "is_exist": false}
```

---

### Extension Management (CRUD)

All extension operations use `POST` and the body parameters below.

#### Add Extension — `?cmd=addextension`

| Parameter | Example |
|-----------|---------|
| `account` | `200` |
| `name` | `Alice` |
| `secret` | `strongpassword` |
| `context` | `from-internal` |
| `type` | `friend` |
| `host` | `dynamic` |
| `dtmfmode` | `rfc2833` |
| `transport` | `udp` |
| `nat` | `no` |
| `qualify` | `yes` |
| `dial` | `PJSIP/200` |
| `mailbox` | `200@device` |
| `callerid` | `Alice <200>` |

```json
{"status": "INSERT OK", "code": 200}
```

#### Update Extension — `?cmd=updateextension`

Same parameters as add. Returns `{"status": "UPDATE OK", "code": 200}`.

#### Delete Extension — `?cmd=deleteextension`

Body: `account=200`

```json
{"status": "DELETE OK", "code": 200}
```

---

### Follow-Me Management (CRUD)

#### Add Follow-Me — `?cmd=addfollowmeextension`

| Parameter | Example | Description |
|-----------|---------|-------------|
| `grpnum` | `200` | Extension number |
| `strategy` | `ringallv2` | Ring strategy |
| `grptime` | `20` | Ring timeout (seconds, max 60) |
| `grplist` | `201,202,09001234567#` | Follow-Me number list |
| `pre_ring` | `0` | Initial ring time (0–60 s) |
| `ringing` | `Ring` | MOH: `Ring` \| `default` \| `none` |
| `annmsg_id` | `0` | Announcement message ID |
| `remotealert_id` | `0` | Remote alert ID |
| `toolate_id` | `0` | Too-late message ID |
| `needsconf` | `` | Confirmation required |
| `postdest` | `` | Post-destination |
| `dring` | `` | Distinctive ring |
| `grppre` | `` | Prefix |

```json
{"status": "INSERT OK", "code": 200}
```

#### Update Follow-Me — `?cmd=updatefollowmeextension`
Same parameters as add.

#### Delete Follow-Me — `?cmd=deletefollowmeextension`
Body: `grpnum=200`

#### Get Follow-Me — `?cmd=getfollowmeextension`
Body: `grpnum=200`

#### Get All Follow-Me — `?cmd=getallfollowmeextensions`
No body required.

---

### Queue Management (FreePBX driver)

Requires `PBX_DRIVER=freepbx`.

#### List Queues — `?cmd=getqueues`

```json
[
  {"extension": "sales", "descr": "Sales Queue", "strategy": "ringall"},
  {"extension": "support", "descr": "Support Queue", "strategy": "leastrecent"}
]
```

#### Add Queue Member — `?cmd=queueaddmember`

| Body param | Example |
|------------|---------|
| `queue` | `sales` |
| `device` | `PJSIP/200` |
| `penalty` | `0` |

```json
{"status": "MEMBER ADDED", "code": 200}
```

#### Remove Queue Member — `?cmd=queueremovemember`

| Body param | Example |
|------------|---------|
| `queue` | `sales` |
| `device` | `PJSIP/200` |

```json
{"status": "MEMBER REMOVED", "code": 200}
```
