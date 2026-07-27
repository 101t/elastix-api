<h1 align="center">Asterisk PBX API</h1>
<p align="center">
  <img src="https://raw.githubusercontent.com/101t/elastix-api/master/docs/cover.png">
</p>

Paquete de API PHP moderno para **Asterisk**, **Issabel PBX** y **FreePBX** — soporta AMI, ARI, PJSIP y canales SIP heredados.

> **Nota de migración:** Elastix (el objetivo original de este paquete) llegó al final de su vida útil y ya no recibe mantenimiento. Este paquete ahora se enfoca en las tres plataformas PBX mantenidas activamente enumeradas anteriormente.

## Requisitos

- PHP 7.4+
- ext-pdo, ext-json, ext-curl
- Asterisk 13+ (puerto AMI 5038)
- Composer (para la instalación moderna basada en drivers)

## Instalación (Composer — recomendado)

```bash
composer require 101t/asterisk-pbx-api
```

O clone e instale las dependencias:

```bash
git clone https://github.com/101t/elastix-api.git
cd elastix-api
composer install
```

Copie y configure el archivo de entorno:

```bash
cp .env.example .env
nano .env          # configure API_SECRET_KEY y PBX_DRIVER como mínimo
```

El punto de entrada de la raíz web es `api.php`. Coloque el repositorio (o un enlace simbólico) dentro de la raíz de documentos de su servidor web.

## Instalación (heredada / sin Composer)

Para su uso en servidores existentes sin Composer, `api.php` vuelve automáticamente al cargador basado en inclusiones compatible con PHP4. No se requieren pasos adicionales más allá de configurar la clave secreta.

---

## Configuración

Todos los ajustes se controlan mediante variables de entorno. Copie `.env.example` a `.env` y complete los valores.

| Variable | Predeterminado | Descripción |
|---|---|---|
| `API_SECRET_KEY` | _(marcador)_ | Secreto aleatorio de 50 caracteres enviado en cada encabezado `Authorization` |
| `PBX_DRIVER` | `asterisk` | Driver activo: `asterisk` \| `issabel` \| `freepbx` |
| `AMI_HOST` | `127.0.0.1` | Host de Asterisk Manager Interface |
| `AMI_PORT` | `5038` | Puerto AMI |
| `AMI_USERNAME` | _(auto-detectar)_ | Usuario AMI; deje vacío para leer automáticamente desde `manager.conf` |
| `AMI_SECRET` | _(auto-detectar)_ | Secreto AMI; deje vacío para leer automáticamente desde `manager.conf` |
| `ARI_ENABLED` | `false` | Habilitar Asterisk REST Interface (Asterisk 12+) |
| `ARI_HOST` | `127.0.0.1` | Host de ARI |
| `ARI_PORT` | `8088` | Puerto HTTP de ARI |
| `CHAN_DRIVER` | `pjsip` | Driver de canal: `pjsip` (Asterisk 13+) o `sip` (heredado) |
| `DB_HOST` | `localhost` | Host de MySQL |
| `DB_PASSWORD` | _(vacío)_ | Contraseña de MySQL |
| `ISSABEL_CONF_PATH` | `/etc/issabel.conf` | Ruta a la configuración de Issabel (lee automáticamente la contraseña de DB) |
| `FREEPBX_CONF_PATH` | `/etc/freepbx.conf` | Ruta a la configuración de FreePBX (lee automáticamente las credenciales de DB) |
| `RECORDINGS_PATH` | `/var/spool/asterisk/monitor` | Directorio para las grabaciones de llamadas |
| `LOG_LEVEL` | `error` | Verbosidad del registro (logging) |
| `CORS_ALLOWED_ORIGINS` | `*` | Valor del encabezado de origen CORS |

Vea `.env.example` para la lista completa.

---

## Drivers

### `asterisk` (predeterminado)
Driver completo de Asterisk con:
- AMI (puerto 5038) para gestión de pares/canales/llamadas
- ARI (puerto 8088, opcional) para operaciones de canales y originación
- Gestión de endpoints **PJSIP** (`chan_pjsip`, Asterisk 13+)
- Gestión de pares **chan_sip** heredados
- Esquema MySQL estilo FreePBX (bases de datos asterisk / asteriskcdrdb)

### `issabel`
Extiende el driver de Asterisk con:
- Arranque desde `/etc/issabel.conf`
- Rutas de uso de disco específicas de Issabel (`/opt/issabel`)
- `getIssabelVersion()` — lee `/etc/issabel-release`
- `getFirewallStatus()` — estado de la cárcel de Fail2Ban

### `freepbx`
Extiende el driver de Asterisk con:
- Arranque desde `/etc/freepbx.conf` (analiza automáticamente los valores de `$amp_conf[]`)
- Soporte de tabla de endpoints PJSIP (tabla `pjsip`, FreePBX 14+)
- Gestión de colas (`getQueues`, `queueAddMember`, `queueRemoveMember`)
- `fwconsoleReload()` — aplicación de configuración moderna
- `getAdvancedSetting($key)` — lee de la tabla `admin`

---

## Endpoints de la API

Todas las solicitudes requieren que el encabezado `Authorization` coincida con `API_SECRET_KEY`.

```
Authorization: <su-clave-secreta>
```

### Autenticación
| Método | URL | Descripción |
|---|---|---|
| POST | `?cmd=auth` | Devuelve las credenciales AMI codificadas en base64 |

### SIP / PJSIP
| Método | URL | Descripción |
|---|---|---|
| POST | `?cmd=sippeers` | Lista todos los pares SIP/PJSIP con su estado |
| POST | `?cmd=sipextensions` | Lista los perfiles de extensiones desde el archivo de configuración |

### Llamadas Activas
| Método | URL | Descripción |
|---|---|---|
| POST | `?cmd=activecall` | Lista de canales en vivo (`core show channels verbose`) |
| POST | `?cmd=channelstatus[&channel=SIP/200]` | Estado del canal AMI |
| POST | `?cmd=parkedcalls` | Lista de llamadas estacionadas |

### Originación de Llamadas (Asterisk / Issabel / FreePBX)
| Método | URL | Parámetros del cuerpo | Descripción |
|---|---|---|---|
| POST | `?cmd=originatecall` | `channel`, `extension`, `context`, `callerid`, `timeout` | Origina una llamada saliente vía AMI |

### Canales ARI (requiere `ARI_ENABLED=true`)
| Método | URL | Descripción |
|---|---|---|
| POST | `?cmd=arichannels` | Lista canales activos vía Asterisk REST Interface |

### Sistema
| Método | URL | Descripción |
|---|---|---|
| POST | `?cmd=systemresources` | Tiempo de actividad, usuarios, carga promedio |
| POST | `?cmd=getharddrivers` | Uso de disco para directorios comunes de PBX |
| POST | `?cmd=getiptablesstatus` | Estado del servicio iptables |

### CDR (Registros Detallados de Llamadas)
| Método | URL | Parámetros del cuerpo | Descripción |
|---|---|---|---|
| POST | `?cmd=cdrreport` | `start_date`, `end_date`, `field_name`, `field_pattern`, `status`, `limit` | Consulta la base de datos CDR |

### Grabaciones
| Método | URL | Descripción |
|---|---|---|
| GET | `?cmd=getwavfile&name=/2024/01/01/file.wav` | Descargar archivo de grabación |

### CRUD de Extensiones
| Método | URL | Descripción |
|---|---|---|
| POST | `?cmd=addextension` | Crear extensión SIP/PJSIP |
| POST | `?cmd=updateextension` | Actualizar extensión |
| POST | `?cmd=deleteextension` | Eliminar extensión (cuerpo: `account`) |

### CRUD de Follow-Me
| Método | URL | Descripción |
|---|---|---|
| POST | `?cmd=addfollowmeextension` | Agregar regla Follow-Me |
| POST | `?cmd=updatefollowmeextension` | Actualizar regla Follow-Me |
| POST | `?cmd=deletefollowmeextension` | Eliminar regla Follow-Me (cuerpo: `grpnum`) |
| POST | `?cmd=getfollowmeextension` | Obtener Follow-Me para una extensión (cuerpo: `grpnum`) |
| POST | `?cmd=getallfollowmeextensions` | Obtener Follow-Me para todas las extensiones |

### Gestión de Colas (solo driver FreePBX)
| Método | URL | Parámetros del cuerpo | Descripción |
|---|---|---|---|
| POST | `?cmd=getqueues` | — | Listar todas las colas de FreePBX |
| POST | `?cmd=queueaddmember` | `queue`, `device`, `penalty` | Agregar agente a la cola |
| POST | `?cmd=queueremovemember` | `queue`, `device` | Eliminar agente de la cola |

---

## Pruebas

```bash
composer test
```

Las pruebas cubren: Carga de configuración, constructores SQL de AsteriskDriver y guardias de seguridad, arranque de IssabelDriver, análisis de configuración de FreePBXDriver.

---

## Documentación

Referencia completa de la API con ejemplos de solicitud/respuesta: [docs/README.md](docs/README.md)

## Contribución

Gracias a las increíbles comunidades de FreePBX, Issabel y Asterisk por toda la información útil.
