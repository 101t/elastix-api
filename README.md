<h1 align="center">Asterisk PBX API</h1>
<p align="center">
  <img src="https://raw.githubusercontent.com/101t/elastix-api/master/docs/cover.png">
</p>

Modern PHP API package for **Asterisk**, **Issabel PBX** and **FreePBX** — supports AMI, ARI, PJSIP and legacy SIP channels.

> **Migration note:** Elastix (the original target of this package) reached end-of-life and is no longer maintained. This package now targets the three actively maintained PBX platforms listed above.

## Requirements

- PHP 7.4+
- ext-pdo, ext-json, ext-curl
- Asterisk 13+ (AMI port 5038)
- Composer (for the modern driver-based install)

## Installation (Composer — recommended)

```bash
composer require 101t/asterisk-pbx-api
```

Or clone and install dependencies:

```bash
git clone https://github.com/101t/elastix-api.git
cd elastix-api
composer install
```

Copy and configure the environment file:

```bash
cp .env.example .env
nano .env          # set API_SECRET_KEY and PBX_DRIVER at minimum
```

The web root entry point is `api.php`. Place the repository (or a symlink) inside your web server's document root.

## Installation (legacy / no Composer)

For use on existing servers without Composer, `api.php` automatically falls back to the legacy PHP4-compatible include-based loader. No extra steps are needed beyond setting the secret key.

---

## Configuration

All settings are controlled via environment variables.  Copy `.env.example` to `.env` and fill in the values.

| Variable | Default | Description |
|---|---|---|
| `API_SECRET_KEY` | _(placeholder)_ | 50-char random secret sent in every `Authorization` header |
| `PBX_DRIVER` | `asterisk` | Active driver: `asterisk` \| `issabel` \| `freepbx` |
| `AMI_HOST` | `127.0.0.1` | Asterisk Manager Interface host |
| `AMI_PORT` | `5038` | AMI port |
| `AMI_USERNAME` | _(auto-detect)_ | AMI username; leave empty to auto-read from `manager.conf` |
| `AMI_SECRET` | _(auto-detect)_ | AMI secret; leave empty to auto-read from `manager.conf` |
| `ARI_ENABLED` | `false` | Enable Asterisk REST Interface (Asterisk 12+) |
| `ARI_HOST` | `127.0.0.1` | ARI host |
| `ARI_PORT` | `8088` | ARI HTTP port |
| `CHAN_DRIVER` | `pjsip` | Channel driver: `pjsip` (Asterisk 13+) or `sip` (legacy) |
| `DB_HOST` | `localhost` | MySQL host |
| `DB_PASSWORD` | _(empty)_ | MySQL password |
| `ISSABEL_CONF_PATH` | `/etc/issabel.conf` | Path to Issabel config (auto-reads DB password) |
| `FREEPBX_CONF_PATH` | `/etc/freepbx.conf` | Path to FreePBX config (auto-reads DB credentials) |
| `RECORDINGS_PATH` | `/var/spool/asterisk/monitor` | Directory for call recordings |
| `LOG_LEVEL` | `error` | Logging verbosity |
| `CORS_ALLOWED_ORIGINS` | `*` | CORS origin header value |

See `.env.example` for the complete list.

---

## Drivers

### `asterisk` (default)
Full Asterisk driver with:
- AMI (port 5038) for peer/channel/call management
- ARI (port 8088, optional) for channel operations and origination
- **PJSIP** endpoint management (`chan_pjsip`, Asterisk 13+)
- Legacy **chan_sip** peer management
- FreePBX-style MySQL schema (asterisk / asteriskcdrdb databases)

### `issabel`
Extends the Asterisk driver with:
- Bootstrap from `/etc/issabel.conf`
- Issabel-specific disk-usage paths (`/opt/issabel`)
- `getIssabelVersion()` — reads `/etc/issabel-release`
- `getFirewallStatus()` — Fail2Ban jail status

### `freepbx`
Extends the Asterisk driver with:
- Bootstrap from `/etc/freepbx.conf` (auto-parses `$amp_conf[]` values)
- PJSIP endpoint table support (`pjsip` table, FreePBX 14+)
- Queue management (`getQueues`, `queueAddMember`, `queueRemoveMember`)
- `fwconsoleReload()` — modern config apply
- `getAdvancedSetting($key)` — reads from the `admin` table

---

## API Endpoints

All requests require the `Authorization` header to match `API_SECRET_KEY`.

```
Authorization: <your-secret-key>
```

### Authentication
| Method | URL | Description |
|---|---|---|
| POST | `?cmd=auth` | Returns base64-encoded AMI credentials |

### SIP / PJSIP
| Method | URL | Description |
|---|---|---|
| POST | `?cmd=sippeers` | List all SIP/PJSIP peers with status |
| POST | `?cmd=sipextensions` | List extension profiles from config file |

### Active Calls
| Method | URL | Description |
|---|---|---|
| POST | `?cmd=activecall` | Live channel list (`core show channels verbose`) |
| POST | `?cmd=channelstatus[&channel=SIP/200]` | AMI channel status |
| POST | `?cmd=parkedcalls` | Parked call list |

### Call Origination (Asterisk / Issabel / FreePBX)
| Method | URL | Body params | Description |
|---|---|---|---|
| POST | `?cmd=originatecall` | `channel`, `extension`, `context`, `callerid`, `timeout` | Originate outbound call via AMI |

### ARI Channels (requires `ARI_ENABLED=true`)
| Method | URL | Description |
|---|---|---|
| POST | `?cmd=arichannels` | List active channels via Asterisk REST Interface |

### System
| Method | URL | Description |
|---|---|---|
| POST | `?cmd=systemresources` | Uptime, users, load average |
| POST | `?cmd=getharddrivers` | Disk usage for common PBX directories |
| POST | `?cmd=getiptablesstatus` | iptables service status |

### CDR (Call Detail Records)
| Method | URL | Body params | Description |
|---|---|---|---|
| POST | `?cmd=cdrreport` | `start_date`, `end_date`, `field_name`, `field_pattern`, `status`, `limit` | Query CDR database |

### Recordings
| Method | URL | Description |
|---|---|---|
| GET | `?cmd=getwavfile&name=/2024/01/01/file.wav` | Download recording file |

### Extension CRUD
| Method | URL | Description |
|---|---|---|
| POST | `?cmd=addextension` | Create SIP/PJSIP extension |
| POST | `?cmd=updateextension` | Update extension |
| POST | `?cmd=deleteextension` | Delete extension (body: `account`) |

### Follow-Me CRUD
| Method | URL | Description |
|---|---|---|
| POST | `?cmd=addfollowmeextension` | Add Follow-Me rule |
| POST | `?cmd=updatefollowmeextension` | Update Follow-Me rule |
| POST | `?cmd=deletefollowmeextension` | Delete Follow-Me rule (body: `grpnum`) |
| POST | `?cmd=getfollowmeextension` | Get Follow-Me for one extension (body: `grpnum`) |
| POST | `?cmd=getallfollowmeextensions` | Get Follow-Me for all extensions |

### Queue Management (FreePBX driver only)
| Method | URL | Body params | Description |
|---|---|---|---|
| POST | `?cmd=getqueues` | — | List all FreePBX queues |
| POST | `?cmd=queueaddmember` | `queue`, `device`, `penalty` | Add agent to queue |
| POST | `?cmd=queueremovemember` | `queue`, `device` | Remove agent from queue |

---

## Testing

```bash
composer test
```

Tests cover: Config loading, AsteriskDriver SQL builders and security guards,
IssabelDriver bootstrap, FreePBXDriver config parsing.

---

## Documentation

Full API reference with request/response examples: [docs/README.md](docs/README.md)

## Contributing

Thank you to the amazing communities around FreePBX, Issabel and Asterisk for all the useful information.
