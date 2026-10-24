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

## Producción (Easypanel)
- Variables:
  - `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://su-dominio`.
  - Conexión a MySQL: `DB_CONNECTION=mysql` y las demás `DB_*`.
  - `NIXPACKS_PHP_ROOT_DIR=/app/public` y `NIXPACKS_PHP_FALLBACK_PATH=/index.php`.
- Comando posterior al despliegue: `php artisan migrate --force && php artisan db:seed --class=CatalogosSeeder --force && php artisan db:seed --class=PermisosSeeder --force && php artisan filament:optimize && php artisan storage:link`.
- Volumen persistente montado en `storage/app`.
- Segundo servicio (*worker*) con `php artisan queue:work` y `php artisan schedule:work`.
