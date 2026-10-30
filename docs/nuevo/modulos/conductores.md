# Módulo 3 – Conductores

Estado: **construido y probado** (9 pruebas propias; 41 en total en verde). Fecha: 2026-10-30.

## Alcance
Conductores (hoja de vida), seguridad social, comparendos, documentos con historial (licencia, planilla, examen médico, manejo defensivo, contrato), estados con novedades, asignación conductor ↔ vehículo y pantalla de vencimientos de conductores.

Reemplaza estas pantallas del legado (detalle en `legado/modulos/operadores.md`):
- Operadores (registro).
- Documentos de operadores (licencia, salud, comparendos).
- Novedades de operadores.

Nuevo frente al legado: asignación explícita conductor ↔ vehículo (antes solo existía implícita en extractos y planillas) y vencimientos de conductores en su propia pantalla (antes no aparecían en ningún panel).

## Pantallas (`/app/{empresa}/...`)
| Ruta | Qué hace | Permiso |
|---|---|---|
| `conductores` | Listado: nombre y documento, celular, licencia (categoría y vencimiento), vehículos asignados, estado y semáforo. Filtros: estado (por defecto oculta retirados), documentos, con/sin vehículo, oficina. | `ver_conductores` |
| `conductores/crear` | Pestañas: Datos personales, Seguridad social (EPS, ARL, pensiones), Comparendos (última consulta SIMIT). Al guardar abre la hoja de vida. | `crear_conductores` |
| `conductores/{id}` | **Hoja de vida**: aviso "No puede operar", aviso de comparendos, resumen (licencia, vehículos), semáforo de los 5 documentos y pestañas Documentos, Vehículos y Novedades. Acción **Cambiar estado**. | `ver_conductores` |
| `conductores/{id}/editar` | Edición de datos (no del estado). | `editar_conductores` |
| `vencimientos-conductores` | Documento vigente de cada conductor activo o suspendido, con Renovar. Insignia roja en el menú con los obligatorios vencidos. | `ver_documentos_conductor` |
| `vehiculos/{id}` (pestaña Conductores) | Asignar o terminar conductores desde el vehículo; el resumen del vehículo muestra sus conductores. | `*_asignaciones_vehiculo` |

## Archivos clave
### Compartido con Flota (nuevo en este módulo)
- `app/Enums/Contratos/TipoDocumento.php`: contrato de los enums de tipos de documento (`corto`, `obligatorio`, `mesesVigencia`, `diasAviso`).
- `app/Models/Concerns/TieneDocumentosConVencimiento.php`: para el dueño (Vehiculo, Conductor): `documentosVigentes()`, `documentoVigente()`, `estadoDocumento()`, `estadoDocumental()`, `bloqueosDocumentales()`, `resumenDocumentos()`, `tiposObligatorios()`.
- `app/Models/Concerns/EsDocumentoConVencimiento.php`: para el documento (DocumentoVehiculo, DocumentoConductor): `estado()`, `diasParaVencer()`, `urlArchivo()` y scopes `vigentes`, `vencidos`, `porVencer`, `enAtencion`, `alDia` (respetan los días de aviso de cada tipo).

### Dominio
- `app/Enums/EstadoConductor.php`: activo, suspendido, retirado.
- `app/Enums/TipoDocumentoConductor.php`: licencia y seguridad social **obligatorios** (bloquean); examen médico, manejo defensivo y contrato **de control** (semáforo sin bloquear). Seguridad social avisa 5 días antes.
- `app/Enums/TipoNovedadConductor.php`: suspensión, retiro, reactivación, reintegro; transiciones en `disponiblesDesde()`.
- `app/Enums/EstadoComparendos.php`: sin comparendos, pendiente, acuerdo de pago, cobro coactivo, embargo (valores del legado).
- `app/Models/Conductor.php`: **`bloqueosOperacion($fecha)`** (estado, documentos obligatorios, licencia que no sea categoría C) y `puedeOperar()`. **El módulo 4 (FUEC) debe usarlo junto con `Vehiculo::bloqueosOperacion()`.** `licencia()`, `tieneComparendos()`, scope `vinculados()` (no retirados).
- `app/Models/DocumentoConductor.php` (categoría de licencia solo para el tipo licencia), `NovedadConductor.php`, `AsignacionVehiculo.php`.
- `app/Models/CategoriaLicencia.php`: `esServicioPublico()` (C1, C2, C3).
- `app/Actions/Conductores/CambiarEstadoConductor.php`: única forma de cambiar el estado; el retiro termina las asignaciones vigentes.
- `app/Actions/Conductores/AsignarVehiculo.php`: asigna (sin duplicar, sin retirados ni desvinculados, un solo principal por vehículo) y `terminar()`.

### Pantallas (Filament)
- `app/Filament/App/Resources/Conductores/ConductorResource.php` (+ `conDocumentosVencidosOFaltantes()`).
- `.../Conductores/Schemas/ConductorForm.php`, `ConductorInfolist.php`, `DocumentoConductorForm.php`.
- `.../Conductores/Acciones/CambiarEstadoConductorAction.php`.
- `.../Conductores/RelationManagers/DocumentosRelationManager.php`, `VehiculosRelationManager.php`, `NovedadesRelationManager.php`, `AsignacionColumnas.php` (columnas y acción Terminar compartidas con el vehículo).
- `.../Conductores/Pages/*`: Listar (conteo por estado), Crear, Ver, Editar.
- `app/Filament/App/Resources/Vehiculos/RelationManagers/ConductoresRelationManager.php`: pestaña Conductores del vehículo.
- `app/Filament/App/Resources/VencimientosConductores/VencimientoConductorResource.php`.

### Permisos (`app/Support/Permisos.php`)
- Recursos nuevos: `conductores`, `documentos_conductor` (ver/crear/editar/eliminar), `novedades_conductor` (ver/crear), `asignaciones_vehiculo` (ver/crear/editar).
- Por defecto: Administrador todo; Gerencia solo ver; Auxiliar ver/crear/editar conductores, documentos y asignaciones + ver novedades; Mantenimiento ver conductores y asignaciones.

### Base de datos
- `database/migrations/2026_10_30_200001_create_conductores.php`: `conductores`, `documentos_conductor`, `novedades_conductor`, `asignaciones_vehiculo` (esquema en `nuevo/datos.md`).
- `database/seeders/DemoConductoresSeeder.php`: 5 conductores ficticios (CC 20000001–20000005) con un caso de cada situación y asignados a los vehículos demo.

### Pruebas
- `tests/Feature/Conductores/ConductoresTest.php`: pantallas y permisos, aislamiento, documento único, bloqueos (planilla vencida, licencia B, suspendido), días de aviso por tipo, retiro/reintegro con asignaciones, reglas de asignación (principal único, sin duplicados, sin desvinculados), vencimientos sin retirados.

## Reglas implementadas
- **Puede operar** = activo + licencia y seguridad social vigentes + licencia categoría C.
- **Comparendos**: se registran como dato con fecha de consulta del SIMIT; muestran aviso pero no bloquean (pendiente decidir con el usuario si alguna situación debe bloquear).
- **Días de aviso** por tipo de documento: planilla 5, revisión preventiva 10, el resto `DIAS_AVISO_VENCIMIENTO` (30).
- **Documento de identidad** único por empresa, sin puntos.
- **Asignaciones**: vigente = sin fecha de fin; terminar conserva el historial; un solo principal por vehículo.

## Pendientes / ideas
- Confirmar en la app actual los campos extra del modal de registro de operadores (el relevamiento no los extrajo todos).
- Consulta automática de comparendos en el SIMIT (hoy es manual, con fecha de consulta).
- Usuario del portal del conductor (`conductores.user_id`, módulo 9).
- Importar conductores y fechas de licencia/salud desde la base del legado.
