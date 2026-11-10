# Monitoreo, alertas y diagnóstico de errores

Tres piezas que trabajan juntas: el **historial de errores** (para diagnosticar), los **chequeos de
salud** (para saber qué servicio falla) y los **avisos** (campana, correo y webhook).

## 1. Historial de errores

Los errores inesperados se siguen agrupando por «huella» (una fila por error, con su contador de
ocurrencias), pero ahora **cada ocurrencia queda registrada** en `ocurrencias_error`: su
referencia (`REF-XXXXXX`), la URL, el método, el usuario y la empresa, y cuándo pasó.

- Se conservan las **50 ocurrencias más recientes** por error (`RegistroErrores::OCURRENCIAS_POR_ERROR`);
  las más viejas se podan solas, así la tabla no crece sin límite.
- En `/admin` → **Estado del sistema**, el buscador encuentra el error por **cualquier referencia**
  del historial, no solo por la última. El botón «Detalle» muestra las últimas ocurrencias.

## 2. Chequeos de salud

`App\Support\Sistema\Salud` revisa, sin lanzar excepciones:

| Chequeo | Qué mira |
|---|---|
| Base de datos | Que responda una consulta |
| Caché / Redis | Escribe y lee un valor |
| Almacenamiento | Escribe, lee y borra un archivo |
| Worker de la cola | Latido reciente del servicio `worker` |
| Tareas programadas | Latido reciente del servicio `scheduler` |
| Cola de trabajos | Que no haya trabajos atascados |
| Trabajos fallidos | Que no queden fallidos sin revisar |
| Correo (Brevo) | Fallos de envío en la última hora **y** que las credenciales SMTP autentiquen |
| SMS (Onurix) | Fallos de envío en la última hora |
| Webhooks | Entregas pendientes acumuladas |
| Respaldos | Antigüedad del último respaldo en R2 (si está configurado) |

El SMS se mide por **fallos recientes**, sin llamar al proveedor: es gratis y detecta igual una
caída o la falta de saldo. El correo, además, **abre la conexión SMTP y autentica** (sin enviar
nada, cacheado 15 min si está sano y 2 si falla), porque contar fallos no detecta una credencial
inválida que nadie ha ejercido todavía.

### Endpoint para monitores externos

`GET /salud` devuelve JSON con cada chequeo y responde **200** si todo está sano o **503** si algo
falla, para que un monitor (UptimeRobot, n8n, etc.) avise solo.

- Protégelo con `SALUD_TOKEN`; se envía como cabecera `X-Salud-Token` o `?token=`.
- Si `SALUD_TOKEN` queda vacío, el endpoint es abierto (no recomendado en producción).

### En el panel

`/admin` → **Estado del sistema** abre con la sección «Salud de los servicios» (se despliega sola
si hay algo con problemas).

## 3. Avisos

El vigilante (`ruta:vigilar`, cada 5 minutos en el scheduler) corre los chequeos y, por cada
servicio caído, avisa **máximo una vez por hora y por tipo** en tres canales:

1. **Campana** de los superadministradores.
2. **Correo** a `ALERTAS_CORREO`.
3. **Webhook** a `ALERTAS_WEBHOOK_URL`.

Los errores inesperados avisan igual (la primera vez y, si se repiten, máximo una vez por hora).

### Contrato del webhook

Mismo formato que los webhooks por empresa, así que **el flujo de n8n lo entiende sin cambios**
(ver `n8n-ruta-notificaciones.json`):

- Cabeceras: `X-Ruta-Evento` (`sistema.error` o `sistema.salud`), `X-Ruta-Entrega`,
  `X-Ruta-Firma` (`sha256=` HMAC del cuerpo con `ALERTAS_WEBHOOK_SECRETO`).
- Cuerpo: `{ id, evento, descripcion, fecha, datos }`. En `datos` viaja la referencia, el mensaje,
  dónde ocurrió, la URL y el enlace al panel (para errores), o el chequeo que falló (para salud).

## Variables de entorno

```
ALERTAS_CORREO=            # correo que recibe las alertas
ALERTAS_WEBHOOK_URL=       # webhook que recibe errores y salud
ALERTAS_WEBHOOK_SECRETO=   # firma HMAC del webhook
SALUD_TOKEN=               # token del endpoint /salud
```

## Fallos de despliegue frecuentes

`CONTAINER_ROLE` es la **única** diferencia entre los servicios: la imagen es la misma y el rol
decide si el contenedor sirve la web o procesa la cola. Con un solo worker, **no** se pone
`SCHEDULE_EN_WORKER`: su valor por defecto (`true`) es lo que hace que el worker corra también las
tareas programadas. Esa variable solo se pone en `false` el día que exista un servicio
`CONTAINER_ROLE=scheduler` aparte.

| Servicio | `CONTAINER_ROLE` | `SCHEDULE_EN_WORKER` |
|---|---|---|
| web | `web` (o sin la variable) | no se pone |
| worker | `worker` | no se pone |
| scheduler (solo si existe) | `scheduler` | en el worker: `false` |

| Síntoma | Causa | Arreglo |
|---|---|---|
| «Service is not reachable» en la web | el servicio web quedó con `CONTAINER_ROLE=worker`: no levanta Apache | dejar `web` o borrar la variable |
| `/salud` → `worker` sin señales, trabajos encolados que no bajan | el worker quedó sin `CONTAINER_ROLE` (por defecto arranca como `web`) | poner `worker` |
| `/salud` → `programadas` sin señales | `SCHEDULE_EN_WORKER=false` sin servicio scheduler | borrar esa variable |
| `/salud` vacío al consultarlo con `curl` | se consultó desde el worker, donde no hay Apache | consultar desde el servicio web o por el dominio público |
| Log: `Skipping [...] because the command already ran on another server` | hay dos contenedores con `schedule:work` (dos réplicas); `onOneServer()` evita la doble ejecución | no es un fallo; bajar a una réplica si no era intencional |
| Un cambio de variable «no surte efecto» | la configuración se cachea al arrancar | redeploy del servicio |

> `proveedor_sms` cuenta envíos **registrados** que fallaron: no valida las credenciales de Onurix.
> `proveedor_correo` sí las valida: además de contar fallos, abre la conexión SMTP y autentica sin
> enviar nada (resultado cacheado 15 minutos si está sano, 2 si falla, para que un arreglo se vea
> pronto). Se añadió porque una API key puesta por error en `MAIL_PASSWORD` tuvo el chequeo en `ok`
> mientras el correo estaba caído.
