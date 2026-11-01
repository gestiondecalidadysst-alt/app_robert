# Módulo 6 – Mantenimiento (planes de acción, mantenimientos y plan preventivo)

**Estado:** construido y probado. Tiene 11 pruebas propias y 94 en total, todas en verde en SQLite y en MySQL. Fecha: 2026-11-01.

## Decisiones del usuario (2026-11-01)
1. **Quién hace los mantenimientos:** los hace el propietario. La empresa hace seguimiento con **planes de acción**, que se generan solos cuando el preoperacional o las inspecciones encuentran fallas.
2. **Evidencias:** se pueden cargar archivos (facturas, fotos) de cada intervención.
3. **Quién registra los mantenimientos:**
   - el auxiliar;
   - el rol Mantenimiento;
   - el conductor desde su portal, que siempre debe adjuntar la evidencia.
4. **Preventivo por kilometraje:** se aprobaron los intervalos base propuestos (tabla abajo).
5. **Rutina vencida:** solo alerta y abre un plan de acción. **No bloquea el FUEC.**
6. **Combustible:** queda para después, porque lo paga el propietario.

Reemplaza tres pantallas de la app anterior (ver `legado/modulos/mantenimiento-combustible.md`): «Mantenimientos», «Informe de mantenimientos» y el cronograma de mantenimiento en PDF.

## Planes de acción
**Cuándo se abren solos:**

| Origen | Prioridad | Fecha límite |
|---|---|---|
| Preoperacional con un ítem crítico que no cumple | **Alta** | 1 día |
| Preoperacional o inspección mensual con un ítem no crítico que no cumple | Media | 7 días |
| Rutina preventiva vencida | Media (solo alerta) | 15 días |
| Plan creado a mano (controles, quejas, incidentes) | La que se elija | La que se elija |

**Un solo plan pendiente por vehículo para las fallas de inspección:**
- Una falla nueva se agrega a los hallazgos del plan.
- Una falla repetida suma una **reincidencia** (cuántas veces) en vez de abrir otro plan.
- Una falla crítica sube el plan a prioridad alta.

**Estados:**
- abierto → en ejecución → **por verificar** → **cerrado**. Pasa a «por verificar» cuando se registra un mantenimiento ligado al plan o se le adjunta una evidencia. Lo cierra la empresa.
- Si la empresa no acepta la evidencia, el plan pasa a **rechazado** con el motivo y el conductor lo ve en el portal.
- Para cerrar un plan sin evidencia ni mantenimiento hay que escribir una observación.

**Prioridad alta pendiente:** el preoperacional del vehículo sale **NO APTO** aunque todo cumpla, y por eso no se habilita el extracto.
- Así se garantiza que una falla crítica se corrija y se verifique.
- El PDF del preoperacional y la tarjeta del conductor dicen qué plan lo bloquea.

**Avisos en la campana:**
- Preoperacional NO APTO: a quien tiene `ver_inspecciones`.
- Plan por verificar: a quien tiene `editar_planes_accion`.

## Mantenimientos
- **Datos:**
  - fecha, kilometraje, tipo (preventivo o correctivo) y acción (cambio, ajuste, revisión o reparación), como en la app anterior;
  - actividad, problema, repuestos, taller y responsable;
  - valor y número de factura;
  - rutinas cumplidas y plan de acción atendido;
  - **evidencias**: PDF o imágenes, hasta 10 archivos de máximo 10 MB cada uno.
- **No se editan:** se **anulan** con motivo, y un mantenimiento anulado deja de contar para el plan preventivo.
- **Si cumple rutinas:** el kilometraje es obligatorio y se cierran solos los planes preventivos pendientes de esas rutinas.

## Plan preventivo
- **Kilometraje actual:** el mayor reportado en preoperacionales, inspecciones o mantenimientos (`Vehiculo::kilometrajeActual()`).
- **Próximo mantenimiento de cada rutina:** el último mantenimiento con esa rutina más el intervalo, en km o en meses, lo que ocurra primero.
- **Semáforo:**
  - **Vencido**.
  - **Próximo:** faltan menos de 1.000 km o 15 días.
  - **Al día**.
  - **Sin registro:** no hay un mantenimiento de esa rutina que sirva de punto de partida.
- **Alcance de las rutinas:** toda la flota, una clase de vehículo o un vehículo. Si dos rutinas tienen el mismo nombre, gana la más específica, y los mantenimientos hechos con la rutina general siguen contando.
- **Revisión de vencimientos:**
  - al registrar un preoperacional o un mantenimiento, porque cambia el kilometraje;
  - cada día a las 6:10 a. m. con `ruta:mantenimiento` (el worker con `schedule:work`), para los vencimientos por fecha.

**Rutinas base** (`app/Support/Mantenimiento/RutinasBase.php`). Son de referencia y cada empresa debe ajustarlas al manual del fabricante:

| Rutina | km | meses |
|---|---|---|
| Aceite de motor y filtro de aceite | 5.000 | 3 |
| Filtro de combustible | 10.000 | 6 |
| Filtro de aire | 20.000 | 12 |
| Revisión de frenos (pastillas, bandas, líquido) | 10.000 | 6 |
| Alineación, balanceo y rotación de llantas | 10.000 | 6 |
| Suspensión y dirección | 20.000 | 12 |
| Refrigerante y mangueras | 40.000 | 24 |
| Aceite de caja y diferencial | 40.000 | 24 |
| Correa de distribución o accesorios | 60.000 | — |
| Batería y sistema eléctrico | 20.000 | 12 |

La **revisión preventiva bimensual** sigue controlándose como documento del vehículo.

## Pantallas
| Dónde | Qué hace | Permiso |
|---|---|---|
| Mantenimiento → **Planes de acción** | Pestañas: pendientes, por verificar, cerrados y todos. Los de prioridad alta van primero. Insignia en el menú con los pendientes. **Nuevo plan de acción** para crearlos a mano. | `*_planes_accion` |
| Plan de acción (detalle) | Hallazgos (reincidencias), mantenimientos y evidencias. Acciones:<br>• **Registrar mantenimiento**<br>• **Adjuntar evidencia**<br>• **En ejecución**<br>• **Rechazar**<br>• **Verificar y cerrar** | `editar_planes_accion` |
| Mantenimiento → **Mantenimientos** | Listado con filtros. **Registrar mantenimiento**: acepta `?vehiculo=` y `?plan=`, que llenan el vehículo, el tipo, las rutinas y el problema. **Informe PDF** por vehículo (o toda la flota) y fechas, con el total. Detalle con evidencias y la acción **Anular**. | `*_mantenimientos` |
| Mantenimiento → **Plan preventivo** | Semáforo de toda la flota: kilometraje actual, estado, la rutina más próxima, vencidas y sin registro. | `ver_mantenimientos` |
| Mantenimiento → **Rutinas preventivas** | Lista ordenable arrastrando, con alcance (flota, clase o vehículo) y frecuencia. | `*_rutinas_mantenimiento` |
| Vehículo → pestañas **Mantenimiento** y **Planes de acción** | Plan preventivo del vehículo (semáforo por rutina), historial de mantenimientos y sus planes. | |
| Portal `/conductor/mantenimiento` | **Mantenimiento** en el menú del portal:<br>• Planes pendientes de sus vehículos, con **Registrar arreglo con evidencia**. Si fue rechazado, muestra el motivo.<br>• Registrar un mantenimiento: en el celular se pueden tomar fotos con la cámara, en varias tandas.<br>• Lo que el conductor ha registrado.<br>En «Servicios» aparece un aviso si hay planes de prioridad alta. | conductor del vehículo |

**Evidencias:** se descargan en `/evidencias/{id}`, con sesión iniciada. Las pueden ver:
- los usuarios de la empresa con permiso de ver mantenimientos o planes de acción;
- el conductor que tiene acceso a ese vehículo.

## Archivos clave
- **Modelos:**
  - `app/Models/{RutinaMantenimiento,Mantenimiento,PlanAccion,Evidencia}.php`.
  - El trait `Concerns/TieneEvidencias`.
  - En `Vehiculo`: `kilometrajeActual()`, `planAltaPendiente()`, `mantenimientos()` y `planesAccion()`.
- **Enums:** `TipoMantenimiento`, `AccionMantenimiento`, `PrioridadPlan`, `OrigenPlan` y `EstadoPlan`.
- **Acciones** en `app/Actions/Mantenimiento/`:
  - `RegistrarMantenimiento`
  - `AnularMantenimiento`
  - `GuardarEvidencias`
  - `PlanesDesdeInspeccion` (la llama `RegistrarInspeccion`)
  - `SeguimientoPreventivo`
  - `GestionarPlanAccion`
- **Soporte:**
  - `app/Support/Mantenimiento/PlanPreventivo.php`
  - `app/Support/Mantenimiento/RutinasBase.php`
  - `app/Support/Avisos.php`
  - `app/Support/Pdf/PdfMantenimientos.php` con su vista `pdf/informe-mantenimientos.blade.php`
- **Panel:**
  - `app/Filament/App/Resources/{Mantenimientos,PlanesAccion,RutinasMantenimiento}/*`
  - `app/Filament/App/Pages/MantenimientoPreventivo.php`
  - `Vehiculos/RelationManagers/{Mantenimientos,PlanesAccion}RelationManager.php`
  - Vistas en `resources/views/filament/mantenimiento/*`
- **Portal:**
  - `app/Http/Controllers/Conductor/MantenimientoController.php`
  - `resources/views/conductor/mantenimiento*.blade.php`
  - `conductor/partes/menu.blade.php`
- **Evidencias:** `app/Http/Controllers/EvidenciaController.php`.
- **Comando:** `app/Console/Commands/SeguimientoMantenimiento.php` (`ruta:mantenimiento`).
- **Base de datos:** la migración `2026_11_01_300001_create_mantenimiento.php` es idempotente. El esquema está en `nuevo/datos.md`.
- **Arranque:** las empresas nuevas reciben las rutinas base al crearse (`CrearEmpresa`) y las existentes al desplegar (`ItemsChequeoSeeder`).
- **Datos de demostración:** `DemoPesvSeeder` deja la falla crítica de la demo corregida con un mantenimiento y su plan cerrado.
- **PHP** (`docker/php.ini`): `post_max_size = 110M` y `max_file_uploads = 20`, para subir varias evidencias juntas.

## Permisos
- **Recursos nuevos:**
  - `mantenimientos`: ver, crear y anular.
  - `planes_accion`: ver, crear y editar. Editar significa gestionar: iniciar, rechazar y cerrar.
  - `rutinas_mantenimiento`: ver, crear, editar y eliminar.
- **Roles:**
  - **Mantenimiento:** todo lo anterior.
  - **Auxiliar:** ver y registrar mantenimientos, y ver planes y rutinas.
  - **Gerencia:** solo ver.

## Pendientes / ideas
- **Combustible:** tanqueos y rendimiento en km por galón.
- **Acceso propio para el propietario**, para que suba sus mantenimientos.
- **Indicadores para el PESV** en el tablero de gerencia (módulo 8): planes vencidos, tiempo promedio de cierre, fallas más repetidas y cumplimiento del preventivo.
