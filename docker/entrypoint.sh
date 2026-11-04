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
  php artisan db:seed --class=ItemsChequeoSeeder --force
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
  # Las tareas programadas van en su propio servicio (CONTAINER_ROLE=scheduler). Mientras no exista,
  # el worker también las corre: ponga SCHEDULE_EN_WORKER=false en el worker cuando cree el scheduler.
  if [ "${SCHEDULE_EN_WORKER:-true}" != "false" ]; then
    # En segundo plano y, si se cae, se vuelve a lanzar.
    ( while true; do php artisan schedule:work; echo "Tareas programadas reiniciadas."; sleep 5; done ) &
  fi
  # Cola: primero la fila alta (SMS y correos), luego default y la baja (webhooks).
  # Cada hora el proceso termina para liberar memoria y este ciclo lo vuelve a lanzar enseguida:
  # el contenedor nunca se detiene, así que no depende de que Easypanel lo reinicie.
  while true; do
    php artisan queue:work --queue=alta,default,baja --sleep=3 --tries=3 --max-time=3600
    echo "Worker reiniciado (ciclo de una hora o salida con código $?)."
    sleep 2
  done
fi

if [ "$ROLE" = "scheduler" ]; then
  php artisan optimize
  # Tareas programadas en primer plano: si el proceso se cae, Easypanel reinicia el servicio.
  exec php artisan schedule:work
fi

echo "CONTAINER_ROLE no válido: $ROLE (use web, worker o scheduler)" >&2
exit 1
