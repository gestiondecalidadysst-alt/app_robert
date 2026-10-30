# Módulo 1 – Configuración

Estado: **construido y probado** (22 pruebas automáticas en verde). Fecha: 2026-10-24.

## Alcance
Empresas (multiempresa), oficinas, usuarios, roles y permisos, catálogos nacionales, valor de administración y empresas en convenio, más la auditoría de cambios.

Reemplaza estas pantallas del legado (detalle en `legado/modulos/parametros.md`):
- Parámetros > Menú y Usuarios.
- Mi Empresa.
- Marcas, Líneas, Clases, Carrocería y Combustible.
- EPS, ARL y Nivel académico.
- Empresas afiliadas.

## Cómo entrar (datos de demostración)
La clave de todos los usuarios demo es `Ruta2026*`.

| Usuario | Panel | Rol |
|---|---|---|
| superadmin@ruta.test | `/admin` | Superadministrador de la plataforma |
| admin@demo.test | `/app/cootrasec-demo` | Administrador |
| gerencia@demo.test | `/app/cootrasec-demo` | Gerencia (solo ver) |
| auxiliar@demo.test | `/app/cootrasec-demo` | Auxiliar administrativo |
| admin@ejemplo.test | `/app/transportes-ejemplo` | Administrador de una segunda empresa, para probar el aislamiento |

## Archivos clave
### Multiempresa y permisos
- `app/Models/Concerns/PerteneceAEmpresa.php`:
  - Filtra por la empresa activa y asigna `empresa_id` al crear.
  - `->deEmpresa($e)` consulta una empresa explícita (para consola, colas y seeders).
- `app/Models/Concerns/Auditable.php`: auditoría (spatie/activitylog) con `empresa_id`.
- `app/Http/Middleware/PrepararEmpresaActiva.php`: fija la empresa como *team* de spatie/permission y guarda el último acceso.
- `app/Support/Permisos.php`: **catálogo único de permisos** (`<accion>_<recurso>`) y permisos por defecto de cada rol. Para agregar un módulo nuevo:
  1. Añadir sus recursos aquí.
  2. Ejecutar `php artisan db:seed --class=PermisosSeeder`.
- `app/Policies/Concerns/AutorizaPorPermiso.php`: política genérica. Cada política solo define `RECURSO`.
- `Gate::before` en `AppServiceProvider`: el superadmin pasa todas las validaciones de permisos.

### Acciones (reglas de negocio)
- `app/Actions/Configuracion/CrearEmpresa.php`: crea la empresa, sus 5 roles, la oficina principal y el primer administrador (o vincula uno existente).
- `app/Actions/Configuracion/AsignarRol.php`:
  - Asigna un único rol por empresa.
  - `rolActual($usuario, $empresa)` devuelve el rol del usuario en esa empresa.
- `app/Actions/Configuracion/SincronizarPermisos.php`.
- `app/Support/Nit.php`: dígito de verificación DIAN y formato `800.197.268-4`.

### Modelos
- `Empresa` (tenant), `EmpresaUsuario` (pivote), `User`, `Oficina`, `Rol` (extiende Role de spatie), `Actividad`.
- `ValorAdministracion`, con `vigente($empresa, $claseId, $fecha)`.
- `EmpresaConvenio`.
- Catálogos: `Catalogo` (base) + Departamento, Municipio, TipoIdentificacion, Marca, Linea, ClaseVehiculo, Carroceria, Combustible, TipoServicio, CategoriaLicencia, Eps, Arl, Aseguradora, NivelAcademico.

### Paneles (Filament 5)
- **Configuración de los paneles:**
  - `app/Providers/Filament/AppPanelProvider.php`: panel de empresa `/app/{slug}`, con tenancy, selector de empresa, empresa activa y rol en la barra superior.
  - `app/Providers/Filament/AdminPanelProvider.php`: panel de plataforma `/admin`.
  - `app/Providers/Filament/Concerns/EstiloRuta.php`: tema común (naranja `#C2410C`, Inter, avatar con iniciales generado localmente).
  - `resources/css/ruta.css`: menú lateral azul petróleo y barra activa naranja. Es CSS puro y no requiere compilar con npm.
- **Pantallas de empresa** (`app/Filament/App/Resources/`):
  - Oficinas.
  - Usuarios (crear, editar y quitar de la empresa).
  - Roles (matriz de permisos).
  - Valores de administración.
  - Empresas en convenio.
  - Auditoría.
  - Mi empresa: `app/Filament/App/Pages/Tenancy/EditarEmpresa.php`.
- **Pantallas de plataforma** (`app/Filament/Admin/Resources/`):
  - Empresas (alta con su primer administrador).
  - Usuarios de la plataforma.
  - 14 catálogos nacionales.
- **Formularios compartidos:** `app/Filament/Compartido/FormularioEmpresa.php` y `CamposComunes.php` (municipio con búsqueda en servidor y NIT con cálculo del DV).
- **Inicio provisional** con accesos rápidos: `app/Filament/App/Widgets/Bienvenida.php`. El tablero de gerencia llega en el módulo 8.

### Base de datos
- Migraciones `database/migrations/2026_10_24_11000*`.
- Permisos de spatie con `teams = true` y `team_foreign_key = empresa_id`.
- Seeders:
  - `CatalogosSeeder`: 1.120 municipios DANE (`database/data/municipios_dane.csv`, licencia MIT), marcas, líneas, EPS, ARL, aseguradoras, etc.
  - `PermisosSeeder`.
  - `DemoSeeder`: no corre en producción.

### Pruebas
- `tests/Unit/NitTest.php`: dígito de verificación con NIT reales.
- `tests/Feature/Configuracion/AccesoPantallasTest.php`: todas las pantallas por rol, bloqueo entre empresas, empresa inactiva.
- `tests/Feature/Configuracion/AislamientoEmpresasTest.php`: consultas, creación y auditoría separadas por empresa.
- `tests/Feature/Configuracion/GestionUsuariosRolesTest.php`:
  - Crear un usuario.
  - Vincular un correo existente sin cambiarle la clave.
  - Rol independiente por empresa.
  - Matriz de roles.
  - Alta de empresa.

## Reglas implementadas
- **NIT:** se guarda limpio y el dígito de verificación se calcula automáticamente al guardar.
- **Empresas:**
  - El slug se genera solo y es único.
  - No se borran, solo se desactivan.
  - Una empresa inactiva no aparece en el selector y bloquea el acceso a `/app`.
- **Usuarios:**
  - Un mismo usuario puede pertenecer a varias empresas, con un rol y una oficina distintos en cada una.
  - Si se crea un usuario con un correo existente, se vincula al usuario actual y se conserva su contraseña.
  - Nadie puede quitarse el acceso a sí mismo.
- **Rol Administrador:** no se renombra ni se borra, y conserva siempre los permisos para ver y editar usuarios y roles.
- **Valor de administración:** se guarda con historial (vigente desde) y no admite dos valores para la misma clase en la misma fecha.
- **Auditoría:** todo cambio queda registrado. La carga inicial de catálogos no se audita.

## Pendientes / ideas
- Recuperación de contraseña por correo: la pantalla existe, pero falta configurar el SMTP en `.env`.
- Los permisos de Flota ya están en `Permisos.php`. Cada módulo nuevo agrega los suyos ahí; al desplegar, los roles existentes reciben solo los permisos nuevos.
