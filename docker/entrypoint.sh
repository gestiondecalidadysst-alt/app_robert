#!/bin/sh
# Arranque del contenedor de Ruta.
set -e

cd /var/www/html

if [ -z "$APP_KEY" ]; then
  echo "ERROR: falta la variable APP_KEY. Genérela y agréguela en Easypanel (Environment)." >&2
  exit 1
fi

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/public storage/app/private storage/fonts
chown -R www-data:www-data storage bootstrap/cache

ROLE="${CONTAINER_ROLE:-web}"

if [ "$ROLE" = "web" ]; then
  # Espera a la base de datos (hasta ~60 s).
  i=0
  until php artisan db:show >/dev/null 2>&1 || [ $i -ge 30 ]; do
    echo "Esperando la base de datos..."; i=$((i+1)); sleep 2
  done

  php artisan migrate --force
  php artisan db:seed --class=CatalogosSeeder --force
  php artisan db:seed --class=PermisosSeeder --force
  php artisan db:seed --class=PlantillasContratoSeeder --force
  php artisan ruta:superadmin --si-no-existe || true

  if [ "${SEED_DEMO:-false}" = "true" ]; then
    php artisan db:seed --class=DemoSeeder --force
  fi

  php artisan storage:link >/dev/null 2>&1 || true
  php artisan optimize
  php artisan filament:optimize

  exec apache2-foreground
fi

if [ "$ROLE" = "worker" ]; then
  php artisan optimize
  # Tareas programadas en segundo plano y cola en primer plano.
  php artisan schedule:work &
  exec php artisan queue:work --sleep=3 --tries=3 --max-time=3600
fi

echo "CONTAINER_ROLE no válido: $ROLE (use web o worker)" >&2
exit 1
