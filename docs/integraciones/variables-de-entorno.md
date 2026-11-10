# Variables de entorno en producción

Lista completa de lo que hay que poner en Easypanel. Lo que no está aquí tiene un valor por
defecto razonable y no hace falta tocarlo.

> **Regla de oro:** la configuración se cachea al arrancar. Cambiar una variable **no surte efecto
> hasta el redeploy** del servicio.

## Qué va en cada servicio

`CONTAINER_ROLE` es la **única** diferencia entre los servicios: la imagen es la misma y el rol
decide si el contenedor sirve la web o procesa la cola. Todas las demás variables van **iguales en
todos**: el worker necesita las de correo, R2 y webhooks tanto como la web.

| Servicio | `CONTAINER_ROLE` | `SCHEDULE_EN_WORKER` | Dominio | Volumen |
|---|---|---|---|---|
| web | `web` (o sin la variable) | no se pone | sí, puerto 80 | sí |
| worker | `worker` | no se pone | no | sí |
| scheduler (solo si se crea aparte) | `scheduler` | en el worker: `false` | no | sí |

Con un solo worker **no se pone `SCHEDULE_EN_WORKER`**: su valor por defecto (`true`) es lo que
hace que el worker corra también las tareas programadas.

## Obligatorias

```
APP_NAME="Ruta"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...              # php artisan key:generate --show
APP_URL=https://su-dominio
APP_TIMEZONE=America/Bogota

DB_CONNECTION=mysql
DB_HOST=<host interno de MySQL>
DB_PORT=3306
DB_DATABASE=<base>
DB_USERNAME=<usuario>
DB_PASSWORD=<clave>

CONTAINER_ROLE=web              # o worker
```

Solo en el primer arranque, para crear el superadministrador:

```
SUPERADMIN_NAME="Superadministrador"
SUPERADMIN_EMAIL=<correo>
SUPERADMIN_PASSWORD=<clave de 8 o más caracteres>
SEED_DEMO=false                 # true carga la empresa de demostración
```

### Rotar `APP_KEY` sin perder los datos cifrados

`APP_KEY` cifra en la base las credenciales RNDC y Onurix, las llaves de proveedores de tránsito,
los secretos de webhook, los tokens de firma de contratos y los secretos de 2FA. **No hay que
reescribir nada** si al rotarla se conserva la anterior:

```
APP_KEY=base64:<la nueva>
APP_PREVIOUS_KEYS=base64:<la anterior>
```

Laravel descifra con la clave nueva y, si no puede, prueba con las anteriores; cada registro se
vuelve a cifrar con la nueva al guardarse. Sin `APP_PREVIOUS_KEYS`, todo lo cifrado queda
ilegible. Las claves anteriores se separan por comas y pueden quitarse cuando ya no haga falta.

## Sesión, caché y cola (Redis)

```
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=<host interno de Redis>
REDIS_PORT=6379
REDIS_PASSWORD=<clave o null>
SESSION_SECURE_COOKIE=true      # el dominio va por HTTPS
```

## Correo (Brevo)

```
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=<login SMTP de Brevo>
MAIL_PASSWORD=<clave SMTP>
MAIL_FROM_ADDRESS=<remitente>
MAIL_FROM_NAME="Ruta"
```

> `MAIL_PASSWORD` es la **clave SMTP** (pestaña *SMTP* de Brevo), **no** una API key. Las API keys
> empiezan por `xkeysib-` y con ellas el servidor responde `535 Authentication failed`.

## Respaldos (Cloudflare R2)

```
R2_ACCESS_KEY_ID=
R2_SECRET_ACCESS_KEY=
R2_ENDPOINT=https://<ACCOUNT_ID>.r2.cloudflarestorage.com
R2_BUCKET=<bucket privado>
R2_REGION=auto
BACKUP_ARCHIVE_PASSWORD=<clave del ZIP>
BACKUP_NOTIFICATION_EMAIL=<correo que recibe el aviso de fallo>
BACKUP_NAME=cootrasec
```

Quien hace el respaldo es el **scheduler** (o el worker, si no hay scheduler aparte): sin estas
variables ahí, `backup:run` falla con «bucket must be of type string, null given».

Detalle y restauración: `docs/integraciones/respaldos-r2.md`.

## Alertas y salud

```
ALERTAS_CORREO=<correo que recibe los errores>
ALERTAS_WEBHOOK_URL=<webhook de n8n>
ALERTAS_WEBHOOK_SECRETO=<secreto de la firma HMAC>
SALUD_TOKEN=<token del endpoint /salud>
```

Si `SALUD_TOKEN` queda vacío, `/salud` queda abierto: no se recomienda en producción.
Detalle: `docs/integraciones/monitoreo-y-alertas.md`.

## Notificaciones push del conductor

```
VAPID_SUBJECT=mailto:<correo de contacto>
VAPID_PUBLIC=
VAPID_PRIVATE_PEM_B64=
```

El par se genera una sola vez con `php artisan ruta:generar-vapid`. Al cambiarlo, las
suscripciones existentes dejan de servir: hay que vaciar `suscripciones_push` para que los
conductores vuelvan a activar los avisos.

## Opcionales

```
DIAS_AVISO_VENCIMIENTO=30       # días antes del vencimiento para marcar "por vencer"
APP_VERSION=<etiqueta>          # se pasa como --build-arg; se ve en el panel
DB_DUMP_SSL=false               # true exige TLS en el volcado del respaldo
DB_DUMP_SSL_FLAG=skip-ssl       # ssl-mode=DISABLED si el cliente es el de MySQL 8
```

Las credenciales de Onurix (SMS), del RNDC y de los proveedores de tránsito **no** van aquí: se
escriben dentro de la app, en Configuración → Integraciones de cada empresa.

## Volumen

Monte un volumen en `/var/www/html/storage/app` en **todos** los servicios, para que los logos,
firmas y documentos no se pierdan entre despliegues.
