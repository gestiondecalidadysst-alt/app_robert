# Ruta – Plataforma de transporte especial (multiempresa)

Laravel 13 + Filament 5. Documentación del proyecto en `docs/` (empiece por `docs/ESTADO.md`).

## Instalar (desarrollo)
```
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Abrir en el navegador:
- http://localhost:8000/app: empresas. Usuario `admin@demo.test`.
- http://localhost:8000/admin: plataforma. Usuario `superadmin@ruta.test`.

La clave de los usuarios demo es `Ruta2026*`.

## Pruebas
`php artisan test`

## Producción (Easypanel, con Dockerfile)
1. **Base de datos:** en el proyecto de Easypanel, cree un servicio **MySQL** (por ejemplo `ruta-db`).
2. **Aplicación web:**
   - Cree un servicio **App** con origen GitHub, que apunte a este repositorio (rama `main`).
   - En *Build* elija **Dockerfile**.
   - En *Domains*, el puerto interno es **80**.
3. **Variables de entorno** (*Environment*). La lista completa y qué va en cada servicio está en
   **`docs/integraciones/variables-de-entorno.md`**; lo mínimo para arrancar:
   ```
   APP_NAME="Ruta"
   APP_ENV=production
   APP_DEBUG=false
   APP_KEY=base64:...            # php artisan key:generate --show
   APP_URL=https://su-dominio
   DB_CONNECTION=mysql
   DB_HOST=<host interno de MySQL en Easypanel>
   DB_PORT=3306
   DB_DATABASE=<base>
   DB_USERNAME=<usuario>
   DB_PASSWORD=<clave>
   SUPERADMIN_EMAIL=<su correo>
   SUPERADMIN_PASSWORD=<clave de 8 o más caracteres>
   SEED_DEMO=true                # opcional: empresa de demostración
   ```
4. **Volumen** (*Mounts*): monte un volumen en `/var/www/html/storage/app`, para que los logos y documentos no se pierdan entre despliegues.
5. **Correo (SMTP)** en *Environment* (`MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`): lo usan la recuperación de contraseñas y la firma de contratos. Los SMS (Onurix) se configuran dentro de la app, en Configuración → Integraciones de cada empresa.
6. **Worker:** cree un segundo servicio desde el mismo repositorio, con las mismas variables y `CONTAINER_ROLE=worker`. Procesa la cola (SMS, correos y webhooks). Este servicio no necesita dominio.
7. **Tareas programadas (recomendado):** cree un tercer servicio igual con `CONTAINER_ROLE=scheduler` y ponga `SCHEDULE_EN_WORKER=false` en el worker. Mientras no exista, el worker también corre las tareas programadas.
8. **Alertas (opcional):** `ALERTAS_CORREO=<correo>` recibe los errores inesperados y los problemas de la cola. También llegan a la campana de los superadministradores. El estado se ve en `/admin/estado-sistema`.

**Qué hace el contenedor web al arrancar:**
- Espera a que la base de datos responda y aplica las migraciones.
- Carga los catálogos y los permisos.
- Crea el superadministrador si todavía no existe.
- Optimiza la aplicación y sirve la web con Apache en el puerto 80.

El estado de salud se consulta en `/up`.

Con `SEED_DEMO=true` se crean los usuarios demo con la clave pública `Ruta2026*`. Úselo solo para probar y después cámbielo a `false`.
