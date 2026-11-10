# Respaldos automáticos (Cloudflare R2)

Respaldos diarios con `spatie/laravel-backup` hacia Cloudflare R2 (almacenamiento S3-compatible,
fuera del servidor). Cubren la **base de datos** y los **archivos subidos** (firmas del
representante, PDF de contratos firmados, evidencias y logos).

## Qué se respalda
- Base de datos MySQL (volcado `.sql`).
- `storage/app/private` y `storage/app/public`.
- Todo empaquetado en un ZIP **cifrado** con `BACKUP_ARCHIVE_PASSWORD`.

## Cuándo
Programado en el scheduler (contenedor con `CONTAINER_ROLE=scheduler`):
- `backup:clean` a las 02:10 (borra respaldos viejos según la retención).
- `backup:run` a las 02:30 (crea el respaldo del día).

Retención: 2 días completos, 7 diarios, 4 semanales, 4 mensuales, 2 anuales. Se ajusta en
`config/backup.php` → `cleanup.default_strategy`.

## Configuración (Easypanel → variables de entorno)
1. En Cloudflare: crea un **bucket R2 privado** y un **API Token de R2** (Access Key + Secret).
2. Pega en Easypanel:
   - `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`
   - `R2_ENDPOINT` = `https://<ACCOUNT_ID>.r2.cloudflarestorage.com`
   - `R2_BUCKET` = nombre del bucket
   - `R2_REGION` = `auto`
   - `BACKUP_ARCHIVE_PASSWORD` = contraseña del ZIP (**guárdala aparte; sin ella no se restaura**)
   - `BACKUP_NOTIFICATION_EMAIL` = correo que recibe el aviso si un respaldo falla
3. Verifica manualmente: `php artisan backup:run` y luego `php artisan backup:list`.

## Avisos
Solo se notifica por correo cuando un respaldo **falla**, no está sano o la limpieza falla
(sin spam de éxito diario).

## Cómo restaurar
1. Descarga el ZIP del día desde el bucket R2 (panel de Cloudflare o `aws s3` apuntando al endpoint).
2. Descompártelo con la contraseña (`BACKUP_ARCHIVE_PASSWORD`).
3. Base de datos: importa el `.sql` → `mysql -u USUARIO -p BASE < db-dumps/....sql`.
4. Archivos: copia el contenido de `storage/app/private` y `storage/app/public` a su ruta.

> Prueba una restauración al menos una vez para confirmar que el respaldo sirve de verdad.
