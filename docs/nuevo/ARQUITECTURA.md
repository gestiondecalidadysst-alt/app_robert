# Arquitectura del rediseño

## Stack
- **Backend:** Laravel 13, PHP 8.3+.
- **Panel:** Filament 5 (Livewire + Alpine.js + Tailwind CSS). El tema propio es CSS puro (`resources/css/ruta.css`), sin compilación.
- **Base de datos:** MySQL 8 (o MariaDB 10.11+). En desarrollo y pruebas se usa SQLite.
- **Colas y caché:** driver `database`; Redis es opcional más adelante.
- **Paquetes previstos:**
  - `spatie/laravel-permission` con *teams*, donde el team es la empresa.
  - `spatie/laravel-activitylog` para auditoría.
  - Generador de PDF (dompdf o spatie/laravel-pdf), que se decide en el módulo Operación.
- **Despliegue:** VPS con Easypanel, desde el repositorio de GitHub `gestiondecalidadysst-alt/app_robert` (rama `main`) con el `Dockerfile` del proyecto (PHP 8.3 + Apache, puerto 80). Pasos y variables en `README.md`.
  - Servicio **web** (`CONTAINER_ROLE=web`): al arrancar, `docker/entrypoint.sh` espera la base de datos, migra, carga catálogos y permisos, crea el superadmin (`SUPERADMIN_*`), carga la demo si `SEED_DEMO=true` y optimiza. Salud: `/up`.
  - Servicio **worker** (`CONTAINER_ROLE=worker`): `queue:work` + `schedule:work`.
  - MySQL. Sesión, caché y colas usan la base de datos, sin Redis.
  - Volumen persistente en `/var/www/html/storage/app`.
  - Pruebas automáticas en GitHub Actions (`.github/workflows/tests.yml`).

## Multiempresa
- **Una sola base de datos.** Toda tabla de negocio lleva `empresa_id`.
- **Tenancy nativa de Filament:** las URL quedan como `/app/{empresa:slug}/...`. El selector de empresa va en la barra superior, como en el diseño aprobado.
- **Aislamiento automático:**
  - Trait `App\Models\Concerns\PerteneceAEmpresa`, con scope global por la empresa activa y relleno automático de `empresa_id` al crear.
  - Prueba automatizada obligatoria: un usuario de la empresa A nunca ve datos de la empresa B.
- **Acceso a empresas:** un usuario puede pertenecer a varias empresas (pivote `empresa_user`) y tener un rol distinto en cada una (spatie teams con `team_id = empresa_id`).
- **Tres paneles:**
  - `/admin`: **Superadministrador** de SolucionesApp. Crea empresas y administra catálogos nacionales.
  - `/app`: **Empresa**. Todos los módulos de negocio.
  - `/conductor`: **Portal del conductor**, una PWA. Se construye en el módulo 9.

## Catálogos: globales frente a por empresa
- **Globales** (los administra el superadmin y se comparten entre empresas): departamentos, municipios (DANE), tipos de identificación, marcas, líneas, clases de vehículo, carrocerías, combustibles, tipos de servicio, categorías de licencia, EPS, ARL, niveles académicos y aseguradoras.
- **Por empresa** (llevan `empresa_id`): oficinas, valor de administración por clase, empresas en convenio, ítems de checklist (módulo PESV) y tarifas.

## Convenciones
- **Idioma del dominio:** nombres de modelos, tablas y campos en **español**, sin tildes ni ñ en el código (`Vehiculo`, `vehiculos`, `fecha_vencimiento`). La infraestructura de Laravel queda en inglés (`users`, `created_at`).
- **Nombres:** tablas en plural y snake_case; modelos en singular y PascalCase. Claves foráneas como `<singular>_id`.
- **Montos:** en pesos, como `decimal(14,2)`.
- **Fechas:** `date` o `datetime`. Formato visible `dd/mm/aaaa`, zona `America/Bogota`, locale `es`.
- **Borrado:** catálogos y maestros usan `activo` (boolean) en vez de borrar; los registros de negocio usan *soft deletes*.
- **Reglas de negocio** en clases de acción o servicio (`app/Actions/...`), no en los recursos de Filament.
- **Tema de Filament** según `DECISIONES.md` (estilo flonext):
  - Color primario naranja `#C2410C`.
  - Menú lateral `#1B2B3A`.
  - Fuente Inter.
  - Placas como chip amarillo.

## Estructura de carpetas (relevante)
```
app/
  Actions/<Modulo>/            # reglas de negocio
  Enums/                       # estados y tipos con etiqueta y color (Filament HasLabel/HasColor)
  Filament/Admin/Resources/    # panel superadmin
  Filament/App/Resources/      # panel empresa, agrupados por módulo
  Models/ (+ Concerns/PerteneceAEmpresa.php)
  Policies/
database/migrations, seeders/ (DANE, catálogos, roles)
docs/                          # esta documentación
tests/Feature/<Modulo>/
```

## Cómo correr el proyecto (desarrollo)
```
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```
Usuarios demo (clave `Ruta2026*`; flota demo con placas `DMO101`…`DMO106`): `superadmin@ruta.test` (/admin), `admin@demo.test`, `gerencia@demo.test`, `auxiliar@demo.test` (/app). Pruebas: `php artisan test`.
