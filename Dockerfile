# Ruta – imagen de producción (Laravel 13 + Filament 5) para Easypanel.
# Incluye el cliente de MySQL (mysqldump) porque los respaldos volcan la base, p7zip-full para
# poder abrir el ZIP cifrado con AES en una restauración de urgencia (el `unzip` clásico no sabe
# descifrar AES), y la extensión redis como requisito (sesión, caché y colas van por Redis: si
# falla, debe fallar el build).
# El mismo contenedor sirve la web (CONTAINER_ROLE=web, por defecto)
# o procesa colas y tareas programadas (CONTAINER_ROLE=worker).

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

FROM php:8.3-apache

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public \
    COMPOSER_ALLOW_SUPERUSER=1 \
    LOG_STACK=daily,stderr \
    LOG_DAILY_DAYS=14 \
    LOG_LEVEL=warning

RUN apt-get update \
 && apt-get install -y --no-install-recommends libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libonig-dev unzip \
      default-mysql-client p7zip-full \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" intl zip gd pdo_mysql bcmath exif pcntl opcache \
 && (yes '' | pecl install redis) && docker-php-ext-enable redis \
 && rm -rf /var/lib/apt/lists/* \
 && a2enmod rewrite headers \
 && sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
 && echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf && a2enconf servername

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-ruta.ini

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html

# Marca de construcción: permite ver en la app qué despliegue está corriendo.
# Va fuera de storage/app porque ahí se monta un volumen que taparía el fichero.
ARG APP_VERSION=desconocida
RUN date -u '+%d/%m/%Y %H:%M UTC' > /var/www/html/.build \
 && echo "${APP_VERSION}" > /var/www/html/.version

# Primero los directorios, luego se limpian cachés que pudieran venir del contexto de build
# (un packages.php viejo haría que Laravel busque providers de desarrollo que no existen con
# --no-dev) y por último se publican los assets de Filament.
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/public bootstrap/cache \
 && rm -f bootstrap/cache/*.php \
 && php artisan filament:assets \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod +x docker/entrypoint.sh

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
  CMD [ "sh", "-c", "[ \"${CONTAINER_ROLE:-web}\" != web ] || curl -fsS http://127.0.0.1/up >/dev/null || exit 1" ]

ENTRYPOINT ["docker/entrypoint.sh"]
