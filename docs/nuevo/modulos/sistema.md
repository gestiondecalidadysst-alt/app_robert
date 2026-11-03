# Colas, tareas programadas y manejo de errores

**Estado:** construido y probado el 2026-11-03.
**Pedido del usuario:** mejorar el manejo de las colas y de los errores.

## Procesos (servicios de Easypanel, misma imagen)
| `CONTAINER_ROLE` | Qué hace |
|---|---|
| `web` | Sirve la aplicación. Al arrancar aplica las migraciones y carga catálogos y permisos. |
| `worker` | Procesa la cola: `queue:work --queue=alta,default,baja`, y se reinicia cada hora. Mientras no exista el servicio scheduler, también corre las tareas programadas; cuando se cree el scheduler, ponerle `SCHEDULE_EN_WORKER=false` al worker. |
| `scheduler` (nuevo) | Corre solo las tareas programadas (`schedule:work`) en primer plano. Si el proceso se cae, Easypanel reinicia el servicio. |

## Filas de la cola (`App\Support\Sistema\Colas`)
- **alta:** SMS y correos (`EnviarSms`, `EnviarCorreo`), es decir, los recordatorios de firma y el resumen de gerencia.
- **baja:** webhooks a n8n (`EnviarWebhook`). Ahora van **cifrados**, porque llevan nombres y teléfonos.
- **Lo que no pasa por la cola:** los códigos de firma, los enlaces enviados a mano, los recibos y los mensajes de prueba se envían en el momento.
- **Tareas programadas:** usan `onOneServer()`, así que no se duplican si las corren dos procesos.

## Latidos y vigilante
- **Latidos** (`Support/Sistema/Latidos`):
  - el worker marca el suyo en cada vuelta de la cola (`Queue::looping`, máximo una vez cada 30 s);
  - las tareas programadas lo marcan cada minuto;
  - **sin latido en 10 minutos**, el proceso se considera caído.
- **`ruta:vigilar`** (cada 5 minutos) avisa a los superadministradores en la campana, y por correo si está `ALERTAS_CORREO`, cuando:
  - el worker está caído;
  - hay trabajos esperando más de 10 minutos;
  - aparecen trabajos fallidos nuevos.

  Avisa máximo una vez por hora por cada tipo de problema.

## Errores
| Tipo | Cómo se maneja |
|---|---|
| **De negocio** (validaciones) | La acción lanza un `ValidationException`. El mensaje sale en el campo o en una notificación («No se pudo completar»), y la ventana queda abierta. No se registran como fallas. |
| **Integridad** | Transacciones y bloqueos en los consecutivos (contratos, FUEC y recibos). |
| **Tareas secundarias** | `try/catch` con `report()`: la operación principal se conserva. Los webhooks nunca interrumpen. |
| **Servicios externos** | Quedan en el historial de envíos como fallidos, con el error exacto. Los envíos en cola se reintentan. |
| **403 / 404 / 419 / 429 / 503** | Páginas en español con el diseño de la app (`resources/views/errors`). |
| **Inesperados (500)** | Página en español con el **código de referencia** (REF-XXXXXX). El mismo código queda en el log (contexto `referencia`) y en `errores_sistema`. |

- **`RegistroErrores`** (enganchado en `bootstrap/app.php` con `reportable`):
  - **Agrupación:** agrupa los errores por **huella** (tipo, archivo, línea y mensaje, con los números ignorados) y cuenta las ocurrencias.
  - **Qué guarda:** URL, usuario, empresa y traza.
  - **Alertas:** avisa la primera vez, y si se repite, máximo una vez por hora.
  - **Reapertura:** si el error estaba resuelto, se reabre.
  - **Nunca falla él mismo:** si la base de datos no responde, el error sigue quedando en el log.
- **Referencia por trabajo:** en la cola se genera una referencia nueva para cada trabajo. Los trabajos síncronos conservan la de la petición.
- **Log en producción** (Dockerfile): `LOG_STACK=daily,stderr`, `LOG_DAILY_DAYS=14` y `LOG_LEVEL=warning`. El canal `stderr` es el que muestra Easypanel.
- **Corrección:** al emitir un contrato con envío automático ya no se ocultan las fallas inesperadas. Solo se ignora la falta de datos de contacto (`ValidationException`); lo demás se registra.

## Pantalla «Estado del sistema» (`/admin/estado-sistema`, solo superadministrador)
- **Procesos:** worker y tareas programadas, con la hora de la última señal y si están funcionando o detenidos.
- **Cola:** trabajos pendientes por fila, desde cuándo esperan y cuántos están atascados.
- **Envíos de los últimos 7 días:** SMS y correos fallidos, y webhooks fallidos y pendientes.
- **Trabajos fallidos:** se pueden **reintentar** (uno o todos) o **descartar**.
- **Errores inesperados:**
  - búsqueda por referencia;
  - número de ocurrencias, primera y última vez, empresa y usuario;
  - detalle con la traza;
  - opción de marcar como resuelto (uno o varios).
- El menú muestra un contador con los problemas abiertos.

## Variables nuevas
- `ALERTAS_CORREO`: opcional; correo que recibe las alertas de errores y de la cola.
- `SCHEDULE_EN_WORKER=false`: va en el worker cuando exista el servicio scheduler.

## Código y pruebas
- **Código:**
  - `app/Support/Sistema/{Colas,Latidos,EstadoColas,Referencia,RegistroErrores}.php`
  - `app/Models/ErrorSistema.php`
  - `app/Console/Commands/VigilarSistema.php`
  - `app/Filament/Admin/Pages/EstadoSistema.php`
  - `resources/views/errors/*`
- **Pruebas:** `tests/Feature/Sistema/SistemaTest.php`, con 6 pruebas.
