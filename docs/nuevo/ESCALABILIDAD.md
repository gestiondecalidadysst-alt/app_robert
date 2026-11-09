# Escalabilidad — cómo actuar si crece el tráfico

Guía para cuando la app (multiempresa) deba soportar mucha carga (cientos o miles de usuarios).
Está en orden de impacto: casi siempre basta con los primeros pasos. No optimizar a ciegas: **medir primero**.

## 1. Entender el número correcto
"1000 usuarios conectados" **no** es "1000 peticiones al servidor en el mismo instante". De 1000 personas
con sesión abierta, en un momento dado quizá 20–60 están haciendo clic a la vez; el resto lee o está quieto.
El número que manda para dimensionar es **peticiones por segundo en el pico** y la **CPU/RAM** del servidor,
no los usuarios logueados.

## 2. Señales de que hay que actuar
- Los tiempos de respuesta suben en las horas pico (páginas lentas).
- El contenedor web se queda sin memoria o se reinicia bajo carga.
- La base de datos muestra consultas lentas o se satura (CPU alta, conexiones al tope).
- La cola se acumula (tareas que tardan en procesarse).

Antes de gastar en más servidores, **medir**: tiempos de respuesta y consultas lentas (slow query log).
Opcional: una **prueba de carga** (p. ej. k6) simulando 500–1000 usuarios golpeando a la vez dice con datos
cuántas réplicas hacen falta, sin adivinar.

## Lo que ya está listo en la app (no requiere desarrollo)
- La imagen Docker ya trae la extensión **phpredis** → se puede usar Redis solo cambiando variables.
- El trabajo pesado (correos, SMS, PDF, RNDC, informes, webhooks) va a **cola** y lo procesa el **worker**.
- Hay **índices por `empresa_id`** (clave en multiempresa).
- La app es prácticamente **sin estado**: la sesión y la caché se pueden mover a Redis por configuración.

## 3. Paso 1 — Redis para sesión, caché y cola (el de mayor impacto)
**Problema que resuelve:** hoy `SESSION_DRIVER`, `CACHE_STORE` y `QUEUE_CONNECTION` están en **base de datos**,
así que cada clic de cada usuario lee/escribe sesión y caché en MySQL. Con mucha gente, eso ahoga la base
primero que nada.
**Qué hacer en Easypanel:**
1. Levantar un servicio **Redis**.
2. En los servicios **web y worker**, poner: `SESSION_DRIVER=redis`, `CACHE_STORE=redis`,
   `QUEUE_CONNECTION=redis`, `REDIS_HOST=<nombre-del-redis>` (y `REDIS_PASSWORD` si aplica).
3. Redesplegar (con rebuild/restart; ver nota de OPcache abajo).
**Efecto:** el mismo servidor pasa a aguantar muchísimos más usuarios. Para el escenario realista
(1000 conectados, pocas decenas activos por segundo) **suele ser suficiente con esto + un servidor decente**
(p. ej. 4 vCPU / 8 GB). Además es **requisito** para el Paso 2 (varias réplicas comparten la sesión en Redis).

## 4. Paso 2 — Varias réplicas del servicio web
Cuando un solo contenedor web no da (muchas peticiones por segundo sostenidas):
- Subir el número de **réplicas** del servicio web en Easypanel; el balanceador reparte la carga.
- **Requisito de archivos:** los subidos (firmas, logos, evidencias) deben estar en almacenamiento
  **compartido** por todas las réplicas: el MISMO volumen montado en todas, o `FILESYSTEM_DISK=s3`.
  Con `FILESYSTEM_DISK=local` cada réplica tendría su disco y se perderían entre ellas.
- La sesión ya queda compartida gracias a Redis (Paso 1).

## 5. Paso 3 — Servidor web de mayor concurrencia (opcional)
Hoy es **Apache con mod_php**: por cada petición en curso mantiene un proceso pesado (~30–60 MB) todo el
tiempo que dura la conexión, aunque esté esperando. Con muchas conexiones lentas (p. ej. conductores en el
celular con mala red) la **RAM se agota**.
Alternativas, de menor a mayor salto:
- **PHP-FPM + Nginx** (o Apache en modo *event* con PHP-FPM): Nginx maneja miles de conexiones baratas y
  sirve lo estático eficiente; el PHP lo atiende un **grupo de procesos PHP-FPM** que solo existen mientras
  se ejecuta código, no mientras el usuario tiene la conexión abierta. El salto importante es
  **mod_php → PHP-FPM**, más que "Apache → Nginx".
- **Laravel Octane / FrankenPHP**: mantiene la app "encendida" en memoria entre peticiones (no la arranca en
  cada una). Es el mayor salto de velocidad, pero más complejo de operar (cuidado con estado entre peticiones).
Requiere cambiar la imagen Docker; dejarlo preparado y probado antes de activarlo.

## 6. Paso 4 — Base de datos (el techo real)
MySQL suele ser el cuello de botella antes que PHP:
- Ponerla en una **instancia dedicada** (no en el mismo contenedor de la app).
- **Afinar**: `innodb_buffer_pool_size`, número de conexiones, etc.
- Mantener los **índices** al día (sobre `empresa_id` y las columnas de los filtros frecuentes).
- Si los informes pesan, agregar **réplica(s) de lectura** y mandar esas consultas ahí.
- Mucho más adelante (decenas de miles), se podría separar por empresa (sharding). No es prioridad.

## 7. Paso 5 — Más workers y medición continua
- Subir **más workers** en paralelo si la cola se acumula en los picos.
- Seguir **midiendo** para dimensionar con datos, no por intuición.

## Nota de despliegue (OPcache)
La imagen usa `opcache.validate_timestamps=0`, así que los cambios de código **no** se toman hasta que el
contenedor se **reconstruye/reinicia**. Todo redeploy debe ser rebuild/restart, no solo "guardar variables".

## Resumen operativo
1. Haz el **Paso 1 (Redis)**: barato y cubre de sobra el escenario realista.
2. **Mide** en el pico. Si los tiempos se mantienen, no gastes de más.
3. Si suben: **Paso 2 (réplicas)**. Luego, solo si hace falta, **Paso 3 (Nginx/FPM u Octane)** y
   **Paso 4 (base de datos dedicada/afinada)**.
