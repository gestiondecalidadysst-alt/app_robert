# Módulo 6 – Mantenimiento (planes de acción, mantenimientos y plan preventivo)

**Estado:** construido y probado. La primera parte es del 2026-11-01 y la segunda (plantillas, programa anual y webhooks) del 2026-11-02. Tiene 19 pruebas propias y 102 en total, todas en verde en SQLite y en MySQL.

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

## Segunda parte (2026-11-02): plantillas, plan del vehículo, programa anual y webhooks
### Lo que pidió el usuario
- **Plantillas de plan por clase de vehículo**, y que el cliente también pueda armarlas por marca y línea, o libres.
- **Un cronograma que cumpla el PESV**, de alto nivel, con estándares de calidad y trazabilidad.
- **Avisos por webhook a n8n.**

**Qué pide la norma:**
- **Paso 17 de la Resolución 40595 de 2022 («Mantenimiento y control de vehículos seguros y equipos»):** la organización debe diseñar e implementar planes de mantenimiento preventivo.
- **Reporte a la Supertransporte (anexo técnico, D. Vehículos):**
  - El número de actividades de mantenimiento preventivo **programadas por trimestre** y las **ejecutadas**.
  - La relación de los mantenimientos ejecutados, con su tipo (preventivo o correctivo) y el **establecimiento o centro especializado** que los hizo.

El programa anual está diseñado para producir exactamente esos datos.

### Plantillas y plan del vehículo
- **Plantillas** (Mantenimiento → **Plantillas de plan**): cada una tiene su criterio (**por clase**, **por marca y línea**, o **libre**) y sus rutinas.
  - Las rutinas se ordenan arrastrando y cada una tiene su interruptor de activa. No se borran: se desactivan, para no perder su historial.
  - Hay una plantilla **por defecto**.
  - Acciones: **Asignar a vehículos** y **Duplicar**.
- **Las rutinas de la versión anterior se convirtieron solas** (migración):
  - las de toda la flota pasaron a la plantilla **Plan general**, que queda como la por defecto;
  - las de una clase, a una plantilla de esa clase;
  - las de un vehículo, a un plan personalizado.
- **Plantilla de cada vehículo:** la asignada o, si no tiene, la que le corresponde por sus datos, en este orden: **marca y línea → marca → clase → por defecto** (`PlanPreventivo::plantillaDe`).
- **Plan del vehículo** (`/vehiculos/{id}/plan-mantenimiento`, con el botón «Plan de mantenimiento» en la pestaña Mantenimiento):
  - **Seguir una plantilla:** el vehículo recibe los cambios de la plantilla.
  - **Personalizar:** el vehículo tiene sus propias rutinas. Se pueden cambiar intervalos, quitar rutinas (se desactivan) y agregar otras. Los cambios de las plantillas no lo afectan.
  - **Copiar de otro vehículo.**
  - **Punto de partida:** cuándo y a cuántos km se hizo cada rutina por última vez. Con el botón **«Las que no tienen registro arrancan hoy»** se toma hoy como punto de partida de las que no tienen dato.
- **El punto de partida se guarda como un mantenimiento marcado `registro_inicial`:**
  - Sirve para calcular el plan.
  - **No cuenta** como mantenimiento real: no aparece en el listado (salvo con el filtro), ni en los informes, ni en los indicadores.
- **Km por día del vehículo:** se calculan con sus lecturas de los últimos 90 días (preoperacionales, inspecciones y mantenimientos). Si no hay lecturas suficientes, se usa el valor por defecto de la empresa (Integraciones → Mantenimiento, 150 al empezar).

### Programa anual (Mantenimiento → **Programa anual**)
1. **Generar programa** (año en curso o siguiente):
   - Proyecta cada rutina de cada vehículo de la flota. Cada actividad cae en la primera de estas dos fechas: la que da su intervalo de km según los km por día del vehículo, o la que da su intervalo de meses.
   - **Año en curso:** se programa desde hoy, y lo que ya está vencido se programa para hoy.
   - **Año siguiente:** se supone que lo de este año se hará a tiempo y el ciclo continúa.
   - El programa queda en **borrador**, con quién lo elaboró. Se puede **regenerar**.
2. **Aprobar** (permiso `aprobar_programas_mantenimiento`, que tienen Gerencia y el Administrador):
   - Las fechas quedan como **línea base**, en `fecha_original`, que no cambia más.
   - Queda registrado quién aprobó y cuándo.
   - Si había otra versión aprobada del mismo año, queda **reemplazada** y lo que ya se ejecutó o canceló pasa a la nueva versión.
3. **Trazabilidad de cada actividad:** todo queda en su `historial` (quién, cuándo y qué), que se ve con el botón del reloj.
   - **Ejecución automática:** al registrar un mantenimiento real con la rutina, se ejecuta la actividad pendiente más cercana (hasta 45 días de adelanto). Queda ligada al mantenimiento y a su evidencia, con los días de desviación.
   - **Ajuste automático:** las actividades siguientes de esa rutina se recalculan desde lo ejecutado.
   - **Anular el mantenimiento** devuelve la actividad a «programada».
   - **Reprogramar** y **cancelar** exigen un motivo.
   - **Incorporar vehículo** agrega al programa aprobado un vehículo vinculado después; sus actividades quedan marcadas como «incorporadas».
4. **Indicadores por trimestre**, según la fecha de la línea base:
   - **Programadas:** las que no están canceladas.
   - **Ejecutadas.**
   - **% de cumplimiento a la fecha:** ejecutadas dividido entre las ya exigibles (ejecutadas más pendientes cuya fecha ya pasó).
   - **Vencidas sin ejecutar.**
5. **Descargas:**
   - **Cronograma anual en PDF**, de toda la flota o de un vehículo:
     - Muestra rutinas por 12 meses, con las marcas P (programada), E (ejecutada), R (reprogramada), V (vencida) y C (cancelada).
     - Encabezado con el código del formato, la versión y el estado.
     - Indicadores y firmas de quién elaboró y quién aprobó.
   - **Excel para la Supertransporte**, con tres hojas:
     - el indicador trimestral;
     - las actividades con su trazabilidad;
     - los mantenimientos ejecutados, con su tipo y el establecimiento.
- **Calendario** (Mantenimiento → **Calendario**): vista mensual de las actividades por estado, con filtro por vehículo.
- **Portal del conductor:** muestra los **próximos mantenimientos programados** de sus vehículos (30 días) y los vencidos.
- **Seguimiento diario:** `ruta:mantenimiento`, a las 6:10 a. m., avisa una sola vez por actividad las **próximas** (con los días de anticipación que configure la empresa, 7 al empezar) y las **vencidas**. Envía un resumen a la campana y un webhook por actividad.

### Webhooks (Configuración → **Integraciones → Avisos por webhook (n8n)**)
- **Configuración:**
  - La URL, que se guarda cifrada.
  - Un secreto opcional (cifrado) para firmar los avisos.
  - Los eventos que se quieren recibir.
- **Pruebas e historial:** el botón **Probar webhook** envía un aviso de prueba, y la sección **Últimos avisos enviados** muestra el estado de cada envío y la respuesta HTTP.
- **Eventos:**
  - `mantenimiento.actividad_proxima`
  - `mantenimiento.actividad_vencida`
  - `mantenimiento.registrado`
  - `mantenimiento.programa_aprobado`
  - `plan_accion.creado`
  - `plan_accion.por_verificar`
  - `plan_accion.cerrado`
  - `preoperacional.no_apto`
- **Cada aviso:**
  - Es un `POST` JSON.
  - Su cuerpo trae `{id, evento, descripcion, fecha, empresa: {id, nombre, nit}, datos: {...}}`.
  - Lleva las cabeceras `X-Ruta-Evento` y `X-Ruta-Entrega`, y `X-Ruta-Firma: sha256=<HMAC-SHA256 del cuerpo con el secreto>`.
- **Envío:** va en cola (worker) con 5 intentos, a los 30 s, 2 min, 10 min y 30 min. Cada envío queda en `webhook_entregas`.
- **En n8n:**
  1. Un nodo **Webhook** (POST) recibe el aviso.
  2. Opcional: un nodo **Crypto** (HMAC SHA256 con el secreto) o un nodo **Code** compara la firma con la cabecera.
  3. Un nodo **Switch** reparte por `evento` hacia WhatsApp (Evolution API), correo o Telegram.
- **Datos útiles en los avisos de actividad:** placa, rutina, fecha y km programados, días que faltan, y el responsable (propietario) con su teléfono.

## Pantallas
| Dónde | Qué hace | Permiso |
|---|---|---|
| Mantenimiento → **Planes de acción** | Pestañas: pendientes, por verificar, cerrados y todos. Los de prioridad alta van primero. Insignia en el menú con los pendientes. **Nuevo plan de acción** para crearlos a mano. | `*_planes_accion` |
| Plan de acción (detalle) | Hallazgos (reincidencias), mantenimientos y evidencias. Acciones:<br>• **Registrar mantenimiento**<br>• **Adjuntar evidencia**<br>• **En ejecución**<br>• **Rechazar**<br>• **Verificar y cerrar** | `editar_planes_accion` |
| Mantenimiento → **Mantenimientos** | Listado con filtros. **Registrar mantenimiento**: acepta `?vehiculo=` y `?plan=`, que llenan el vehículo, el tipo, las rutinas y el problema. **Informe PDF** por vehículo (o toda la flota) y fechas, con el total. Detalle con evidencias y la acción **Anular**. | `*_mantenimientos` |
| Mantenimiento → **Plan preventivo** | Semáforo de toda la flota: kilometraje actual, estado, la rutina más próxima, vencidas y sin registro. | `ver_mantenimientos` |
| Mantenimiento → **Plantillas de plan** | Plantillas (clase, marca y línea, o libres) con sus rutinas. Acciones: asignar a vehículos y duplicar. | `*_rutinas_mantenimiento` |
| Vehículo → **Plan de mantenimiento** | Seguir una plantilla o personalizar el plan, copiar de otro vehículo y registrar el punto de partida. | `ver/editar_rutinas_mantenimiento` |
| Mantenimiento → **Programa anual** | Generar, aprobar, regenerar e incorporar vehículos. Muestra indicadores por trimestre y las actividades, con estas acciones:<br>• registrar<br>• reprogramar<br>• cancelar<br>• historial<br>Descarga el PDF y el Excel. | `*_programas_mantenimiento`, `aprobar_programas_mantenimiento` |
| Mantenimiento → **Calendario** | Calendario mensual del programa. | `ver_programas_mantenimiento` |
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
- **Comando:** `app/Console/Commands/SeguimientoMantenimiento.php` (`ruta:mantenimiento`). También envía los avisos del programa (`AvisosPrograma`).
- **Segunda parte:**
  - **Modelos:** `PlantillaMantenimiento`, `ProgramaMantenimiento`, `ActividadMantenimiento` y `WebhookEntrega`.
  - **Enums:** `CriterioPlantilla`, `EstadoPrograma` y `EstadoActividad`.
  - **Acciones:** `PlanDeVehiculo`, `ProgramaAnual` y `AvisosPrograma`.
  - **Soporte:** `Support/Webhooks.php`, `Jobs/EnviarWebhook.php`, `Support/Pdf/PdfProgramaMantenimiento.php` (con su vista `pdf/programa-mantenimiento.blade.php`) y `Support/Mantenimiento/ExportarPrograma.php` (OpenSpout).
  - **Panel:**
    - `Resources/{PlantillasMantenimiento,ProgramasMantenimiento}/*`
    - `Vehiculos/Pages/PlanMantenimientoVehiculo.php`
    - `Pages/CalendarioMantenimiento.php`
  - **Base de datos:** la migración `2026_11_02_100001_create_programa_mantenimiento.php` es idempotente y convierte las rutinas existentes.
- **Base de datos:** la migración `2026_11_01_300001_create_mantenimiento.php` es idempotente. El esquema está en `nuevo/datos.md`.
- **Arranque:** las empresas nuevas reciben las rutinas base al crearse (`CrearEmpresa`) y las existentes al desplegar (`ItemsChequeoSeeder`).
- **Datos de demostración:** `DemoPesvSeeder` deja la falla crítica de la demo corregida con un mantenimiento y su plan cerrado.
- **PHP** (`docker/php.ini`): `post_max_size = 110M` y `max_file_uploads = 20`, para subir varias evidencias juntas.

## Permisos
- **Recursos nuevos:**
  - `mantenimientos`: ver, crear y anular.
  - `planes_accion`: ver, crear y editar. Editar significa gestionar: iniciar, rechazar y cerrar.
  - `rutinas_mantenimiento` (ahora «Plantillas y planes de mantenimiento»): ver, crear, editar y eliminar.
  - `programas_mantenimiento`: ver, crear, editar y **aprobar**. «Aprobar» es una acción nueva del catálogo de permisos.
- **Roles:**
  - **Mantenimiento:** todo lo anterior.
  - **Auxiliar:** ver y registrar mantenimientos, y ver planes y rutinas.
  - **Gerencia:** solo ver, y además **aprobar** el programa anual.
  - El rol Mantenimiento ve, genera y edita el programa; no lo aprueba.

## Pendientes / ideas
- **Combustible:** tanqueos y rendimiento en km por galón.
- **Acceso propio para el propietario**, para que suba sus mantenimientos.
- **Indicadores para el PESV** en el tablero de gerencia (módulo 8): planes vencidos, tiempo promedio de cierre, fallas más repetidas y cumplimiento del preventivo.
