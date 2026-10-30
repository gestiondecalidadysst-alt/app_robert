# Módulo 2 – Flota

Estado: **construido y probado** (10 pruebas propias; 32 en total en verde). Fecha: 2026-10-30.

## Alcance
Propietarios, vehículos (hoja de vida), documentos con historial, estados con novedades y pantalla de vencimientos.

Reemplaza estas pantallas del legado (detalle en `legado/modulos/vehiculos.md`):
- Propietarios.
- Vehículos y Ficha técnica (ahora una sola hoja de vida con pestañas).
- Control de documentos (ahora pantalla **Vencimientos** + pestaña Documentos del vehículo).
- Novedades de vehículos (ahora acción **Cambiar estado** + pestaña Novedades).

Queda para otros módulos: historial de mantenimientos en la hoja de vida (módulo 6) y cronograma de inspección preventiva en PDF (módulos 5/6).

## Pantallas (`/app/{empresa}/...`)
| Ruta | Qué hace | Permiso |
|---|---|---|
| `vehiculos` | Listado con placa, estado y semáforo de documentos. Filtros: estado (por defecto oculta desvinculados), documentos (vencidos o sin registrar / por vencer), clase, propietario. | `ver_vehiculos` |
| `vehiculos/crear` | Formulario en pestañas: Identificación, Tarjeta de propiedad, Ficha técnica, Vinculación. Al guardar abre la hoja de vida. | `crear_vehiculos` |
| `vehiculos/{id}` | **Hoja de vida**: aviso "No puede operar" con los motivos, resumen, semáforo de los 6 documentos, datos en pestañas, y debajo las pestañas Documentos (historial) y Novedades. Acción **Cambiar estado**. | `ver_vehiculos` |
| `vehiculos/{id}/editar` | Edición de datos (no del estado). | `editar_vehiculos` |
| `propietarios` | CRUD en modal; cuenta de vehículos y enlace al listado filtrado. No se borra un propietario con vehículos. | `*_propietarios` |
| `vencimientos` | Documento vigente de cada vehículo en flota, ordenado por vencimiento; contadores arriba; acción **Renovar**. Insignia roja en el menú con los vencidos. | `ver_documentos_vehiculo` |

## Archivos clave
### Dominio
- `app/Enums/EstadoVehiculo.php`: operando, suspendido, taller, desvinculado (etiqueta, color, ícono).
- `app/Enums/TipoDocumentoVehiculo.php`: SOAT, técnico-mecánica, revisión preventiva (bimestral), póliza RC contractual, póliza RC extracontractual, tarjeta de operación. Define si usa aseguradora y los meses de vigencia sugeridos.
- `app/Enums/EstadoDocumento.php`: semáforo vigente / por vencer / vencido / sin registrar. `paraVencimiento($fecha)` calcula el estado.
- `app/Enums/TipoNovedadVehiculo.php`: suspensión, taller, desvinculación, reactivación, vinculación; `disponiblesDesde($estado)` define las transiciones permitidas.
- `app/Models/Propietario.php`, `Vehiculo.php`, `DocumentoVehiculo.php`, `NovedadVehiculo.php` (todos con `PerteneceAEmpresa` + `Auditable`; los tres primeros con *soft deletes*).
- `Vehiculo`:
  - `documentosVigentes()`, `documentoVigente($tipo)`, `estadoDocumento($tipo)`, `estadoDocumental()` (peor estado).
  - **`bloqueosOperacion($fecha)`**: lista de motivos por los que no puede operar (estado ≠ operando, documento vencido o sin registrar). `puedeOperar($fecha)`. **El módulo 4 (FUEC) debe usar esto antes de expedir.**
  - `normalizarPlaca()`: `abc-123` → `ABC123`.
- `DocumentoVehiculo::scopeVigentes()`: solo el vigente de cada tipo por vehículo (el de vencimiento más lejano).
- `app/Actions/Flota/CambiarEstadoVehiculo.php`: única forma de cambiar el estado; valida la transición, exige observación y crea la novedad.

### Pantallas (Filament)
- `app/Filament/App/Resources/Vehiculos/VehiculoResource.php`: tabla, filtros y `conDocumentosVencidosOFaltantes()` (consulta reutilizable).
- `.../Vehiculos/Schemas/VehiculoForm.php`: formulario; valida formato y unicidad de placa por empresa (incluye eliminados).
- `.../Vehiculos/Schemas/VehiculoInfolist.php`: hoja de vida.
- `.../Vehiculos/Schemas/DocumentoForm.php`: campos de documento (compartidos con Vencimientos).
- `.../Vehiculos/Acciones/CambiarEstadoAction.php`: modal "Cambiar estado".
- `.../Vehiculos/RelationManagers/DocumentosRelationManager.php`: historial agrupado por tipo, "Reemplazado" para los no vigentes, Renovar, ver archivo (URL firmada de 10 min).
- `.../Vehiculos/RelationManagers/NovedadesRelationManager.php`: solo lectura.
- `.../Vehiculos/RelationManagers/ConductoresRelationManager.php`: conductores asignados (módulo 3).
- `.../Vehiculos/Pages/*`: Listar (subtítulo con conteo por estado), Crear, Ver, Editar.
- `app/Filament/App/Resources/Propietarios/PropietarioResource.php` (+ `campos()` reutilizado para crear propietario desde el vehículo).
- `app/Filament/App/Resources/Vencimientos/VencimientoResource.php` + `Widgets/ResumenVencimientos.php`.

### Permisos (`app/Support/Permisos.php`)
- Recursos nuevos: `propietarios`, `vehiculos`, `documentos_vehiculo` (ver/crear/editar/eliminar), `novedades_vehiculo` (ver/crear = cambiar estado).
- Por defecto: Administrador todo; Gerencia solo ver; Auxiliar ver/crear/editar propietarios, vehículos y documentos + ver novedades; Mantenimiento ver vehículos, documentos y novedades.

### Base de datos
- `database/migrations/2026_10_30_100001_create_flota.php`: tablas `propietarios`, `vehiculos`, `documentos_vehiculo`, `novedades_vehiculo` (esquema en `nuevo/datos.md`).
- `database/seeders/DemoFlotaSeeder.php`: 3 propietarios ficticios y 6 vehículos (`DMO101`…`DMO106`) con un caso de cada semáforo y uno en taller. Lo llama `DemoSeeder`.

### Pruebas
- `tests/Feature/Flota/FlotaTest.php`: pantallas por rol, aislamiento entre empresas, placa (normalización, formato, unicidad por empresa), semáforo y bloqueos, renovación con historial, cambio de estado y transiciones inválidas, desvinculados fuera de vencimientos, y que el despliegue no borre los ajustes de roles.

## Reglas implementadas
- **Placa:** se guarda sin espacios ni guiones y en mayúsculas; formato `ABC123` (o `ABC12D` para motos); única por empresa. El número interno también es único por empresa.
- **Documentos:** renovar = registro nuevo; el anterior queda como "Reemplazado". Vigente = el de vencimiento más lejano. Los 6 tipos son obligatorios para operar.
- **Semáforo:** vencido si la fecha ya pasó; por vencer si vence en los próximos días de aviso del tipo (revisión preventiva 10; el resto `DIAS_AVISO_VENCIMIENTO`, 30 por defecto); sin registrar si no existe. La lógica es compartida con Conductores (ver `conductores.md`, sección Compartido).
- **Archivos de documentos:** en disco privado (`storage/app/private/documentos-vehiculo/{empresa}`), solo PDF o imagen de hasta 5 MB; se abren con enlace firmado temporal.
- **Estado:** no se edita en el formulario; solo con "Cambiar estado", que exige observación y deja historial (estado anterior, nuevo, fecha, reactivación prevista, usuario).
- **Desvinculados:** no aparecen por defecto en el listado ni en Vencimientos.
- **Fechas:** todos los selectores de fecha de la app muestran `dd/mm/aaaa` (`AppServiceProvider`).

## Pendientes / ideas
- Alertas por correo o WhatsApp de vencimientos (worker + tareas programadas): pendiente de SMTP.
- Reactivación automática al llegar la fecha prevista (hoy solo es informativa).
- Importar propietarios, vehículos y fechas de documentos desde la base del legado.
- Días de aviso configurables por empresa (hoy es global).
