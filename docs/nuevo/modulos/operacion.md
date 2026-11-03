# Módulo 4 – Operación (contratos y FUEC)

Estado: **construido y probado** (9 pruebas propias; 50 en total en verde). Fecha: 2026-10-30.

## Norma aplicada
- **Resolución 6652 de 2019** del Ministerio de Transporte (vigente): contenido mínimo del FUEC (art. 3), número de 21 dígitos (art. 4), firma del gerente (art. 7), disposición total (art. 9), convenios (art. 13), vigencia ≤ contrato (art. 15).
- **Decreto 1048 de 2026** (5 de agosto de 2026) ordena expedir el extracto a través del RUNT, pero el mecanismo aún no está reglamentado; hay un proyecto de resolución (junio de 2026) con transición de 3 meses. Mientras tanto se expide en PDF con la 6652. El modelo guarda todo lo que el RUNT pedirá (contratos, vehículos, conductores, convenios).

## Alcance
Clientes (contratantes), contratos con pagos y saldo, y expedición del FUEC con validación en el servidor, PDF con QR y verificación pública.

Reemplaza estas pantallas del legado (detalle en `legado/modulos/contratos.md` y `extractos.md`):
- Elaborar contratos eventuales, Bitácora, Administrar (duplicado) → **Contratos**.
- Abonos / pagos → pestaña **Pagos** del contrato.
- Generar extracto, Programador, Programador final (duplicados) → **FUEC** (listado con filtros + Expedir FUEC).
- Extractos > Administración (404 en el legado) y **Modificar consecutivo** → eliminados: el número lo genera el servidor; un FUEC errado se **anula**.

## Pantallas (`/app/{empresa}/...`)
| Ruta | Qué hace | Permiso |
|---|---|---|
| `fuec` | Listado: número, placa, conductor, contrato, vigencia, estado. Filtros: estado (vigentes hoy, programados, terminados, anulados), vehículo, fechas de expedición. Subtítulo con vigentes y expedidos hoy. | `ver_fuecs` |
| `fuec/expedir` (`?contrato=ID`) | Formulario con **revisión en vivo**: muestra qué bloquea antes de enviar. Al elegir el contrato copia vigencia, origen, destino y recorrido; al elegir el vehículo propone sus conductores asignados (principal primero). ⚠ marca vehículos y conductores que hoy no pueden operar. Botón **Usar fecha máxima** cuando un documento vence dentro de la vigencia. | `crear_fuecs` |
| `fuec/{id}` | Detalle (de la copia guardada), **Descargar PDF**, **Ver verificación**, **Anular**. | `ver_fuecs`, `anular_fuecs` |
| `contratos` | Listado con saldo y número de FUEC. Filtros: estado, con saldo, objeto, forma de pago, cliente. | `ver_contratos` |
| `contratos/crear`, `/{id}/editar` | Formulario por secciones; cliente nuevo desde el mismo formulario. El número lo asigna el sistema. | `crear_contratos`, `editar_contratos` |
| `contratos/{id}` | Detalle con valor, pagado y saldo; pestañas **FUEC** y **Pagos**; acciones Expedir FUEC y Anular. | `ver_contratos`, `anular_contratos` |
| `clientes` | CRUD en modal; NIT con dígito de verificación automático; no se borra un cliente con contratos. | `*_clientes` |
| `/verificar/fuec/{codigo}` | **Pública** (sin sesión, código QR del PDF): estado, número, placa, vigencia, origen-destino, contrato y conductores con el documento oculto salvo los últimos 4 dígitos. Límite 30 consultas por minuto. | — |

## Archivos clave
### Dominio
- `app/Enums/ObjetoContrato.php` (escolar, empresarial, salud, turismo, grupo específico), `FormaPago.php`, `MetodoPago.php`, `EstadoVigencia.php` (programado, vigente, terminado, anulado; calculado por fechas).
- `app/Models/Cliente.php` (`identificacion` formateada, DV si es NIT), `Contrato.php` (`estado()`, `totalPagado()`, `saldo()`, `numero_formateado`, scopes `noAnulados`, `disponiblesParaFuec`), `PagoContrato.php`, `Fuec.php` (`datos` = copia al expedir, `estado()`, `numero_formateado`, `urlVerificacion()`).
- `app/Models/Empresa.php`: `faltantesHabilitacion()` (código territorial, resolución y año de habilitación, que forman el número).
- `app/Actions/Operacion/CrearContrato.php`: consecutivo por empresa con bloqueo (`lockForUpdate`).
- `app/Actions/Operacion/RegistrarPago.php`: no admite pagos a contratos anulados ni mayores que el saldo. Bloquea el contrato y la empresa, y asigna el recibo de caja: el consecutivo, el código de verificación y el saldo antes y después del pago.
- `AnularPago.php`: los pagos no se borran, se anulan con un motivo. `EnviarRecibo.php` envía el recibo por correo (PDF adjunto) y por SMS (enlace), y el estado de cuenta o el paz y salvo por correo.
- `app/Actions/Operacion/AnularContrato.php`: exige motivo y que no tenga FUEC sin anular.
- **`app/Actions/Operacion/ExpedirFuec.php`**: única forma de crear un FUEC.
  - `validar($empresa, $datos)`: errores por campo (la pantalla los muestra en vivo).
  - `numero(...)`: 21 dígitos = territorial (3) + resolución (4) + año de habilitación (2) + año de expedición (4) + contrato (4) + consecutivo del FUEC en el contrato (4). Bloques largos toman sus últimos dígitos; cortos se completan con ceros.
  - `fechaMaxima(...)`: primer vencimiento de documentos dentro de la vigencia pedida.
  - Copia en `datos`: empresa, contrato, vehículo (con tarjeta de operación), conductores (con licencia), origen, destino y convenio.
- `app/Actions/Operacion/AnularFuec.php`: motivo obligatorio; el número no se reutiliza.
- `app/Support/Pdf/PdfFuec.php` + `resources/views/pdf/fuec.blade.php`: PDF carta de 2 páginas con el **formato oficial** (ficha técnica anexa a la Res. 1069 de 2015, mantenida por la 6652 de 2019):
  - **Anverso**: "Ministerio de Transporte" + sello **Vigilado SuperTransporte** (`resources/images/vigilado-supertransporte.png`, entregado por el usuario) + logo de la empresa; título con el número; razón social y NIT; contrato No.; contratante y NIT/CC; objeto; origen-destino describiendo el recorrido; convenio / consorcio / unión temporal con; vigencia del contrato (día, mes, año inicial y de vencimiento); características del vehículo (placa, modelo, marca, clase, número interno, tarjeta de operación); datos del conductor 1, 2 y 3 (nombres y apellidos, cédula, licencia, vigencia); responsable del contratante (nombres, cédula, teléfono, dirección); dirección, teléfono y correo de la empresa; firma y sello del gerente. Se agrega un QR de verificación pequeño en el pie.
  - **Reverso**: instructivo para la determinación del número consecutivo, tabla de códigos de las Direcciones Territoriales y el ejemplo oficial.
  - dompdf con subconjunto de fuentes (~40 KB), QR con chillerlan/php-qrcode por GD; marca "ANULADO" si aplica.
- `app/Support/DireccionesTerritoriales.php`: los 20 códigos oficiales (Córdoba - Sucre = 223). «Mi empresa» los ofrece en un selector y `ExpedirFuec` rechaza un código que no esté en la tabla.
- `app/Http/Controllers/VerificarFuecController.php` + `resources/views/fuec/verificar.blade.php` + ruta `fuec.verificar` en `routes/web.php`.

### Reglas al expedir (`ExpedirFuec::validar`)
1. La empresa tiene dirección territorial (código de la tabla oficial), resolución y año de habilitación ("Mi empresa").
2. El contrato existe, no está anulado y la vigencia del FUEC está dentro de la del contrato.
3. El FUEC no inicia en el pasado.
4. El vehículo puede operar **al iniciar** (`Vehiculo::bloqueosOperacion`).
5. Entre 1 y 3 conductores distintos que pueden operar al iniciar (`Conductor::bloqueosOperacion`: estado, licencia C vigente, planilla vigente).
6. Ningún documento obligatorio del vehículo o los conductores vence antes de terminar el FUEC; si alguno vence, el error indica la **fecha máxima posible**.
7. El convenio (si se indica) es de la empresa.
8. **Sin cruces de horario** (pedido del usuario, 2026-11-01): el vehículo y cada conductor no pueden estar en otro FUEC no anulado cuyo horario se cruce. Cada FUEC tiene `hora_inicio` y `hora_fin` (se copian del contrato y se pueden cambiar). Vacías = todo el día (00:00 a 24:00). Si la hora final es menor o igual que la inicial, el servicio pasa la medianoche. Un servicio que termina a las 15:00 no se cruza con uno que empieza a las 15:00. El error dice qué FUEC y qué horario ocupa al vehículo o al conductor. Cálculo en `app/Support/Operacion/Horario.php`; migración `2026_11_01_200001_add_horario_to_fuecs.php`.

### Pantallas (Filament)
- `app/Filament/App/Resources/Fuecs/FuecResource.php`, `Schemas/FuecForm.php` (revisión en vivo, sugerencia de conductores), `Schemas/FuecInfolist.php`, `Acciones/AccionesFuec.php` (PDF, anular), `Pages/ExpedirFuec.php` (lleva los errores a cada campo), `ListarFuecs.php`, `VerFuec.php`.
- `app/Filament/App/Resources/Contratos/ContratoResource.php`, `Schemas/ContratoForm.php`, `ContratoInfolist.php`, `RelationManagers/PagosRelationManager.php`, `FuecsRelationManager.php`, `Pages/*` (Crear usa `CrearContrato`; Editar impide dejar FUEC por fuera de las fechas nuevas).
- `app/Filament/App/Resources/Clientes/ClienteResource.php` (+ `campos()` reutilizado en el contrato).
- `app/Filament/Compartido/ErroresDeNegocio.php`: muestra como notificación el rechazo de una regla de negocio dentro de una acción (aplicado también en Flota y Conductores).

### Permisos (`app/Support/Permisos.php`)
- Acción nueva **`anular`** (en la política genérica `AutorizaPorPermiso::anular`).
- Recursos: `clientes` (ver/crear/editar/eliminar), `contratos` (ver/crear/editar/anular), `pagos_contrato` (ver/crear/eliminar), `fuecs` (ver/crear/anular).
- Por defecto: Administrador todo; Gerencia ver; Auxiliar ver/crear/editar clientes y contratos, registrar pagos y expedir FUEC (no anular).

### Base de datos
- `database/migrations/2026_10_30_300001_create_operacion.php`: `clientes`, `contratos`, `pagos_contrato`, `fuecs`, `fuec_conductor` (esquema en `nuevo/datos.md`).
- `database/seeders/DemoOperacionSeeder.php`: completa datos de habilitación ficticios de la empresa demo (223 Córdoba-Sucre / 0123 / 2015), 2 clientes, 2 contratos (escolar a crédito con abono, empresarial pagado) y 1 FUEC.
- Dependencias nuevas: `barryvdh/laravel-dompdf`, `chillerlan/php-qrcode`. Se actualizó `symfony/mime` (CVE-2026-45070).

### Pruebas
- `tests/Feature/Operacion/OperacionTest.php`: pantallas y permisos, aislamiento, número de 21 dígitos, expedición (copia, consecutivo por contrato, orden de conductores, PDF, verificación pública sin datos completos del conductor), bloqueos (documentos, vigencia del contrato, fecha pasada, conductor repetido, habilitación incompleta, fecha máxima), expedición desde la pantalla con errores en el campo, contratos (consecutivo por empresa, pagos y saldo, anulación en cascada), conductor retirado.

## Pendientes / ideas
- **RUNT**: cuando el Ministerio reglamente el Decreto 1048 de 2026, enviar cada FUEC al RUNT (servicio web) y guardar su respuesta; hoy no hay especificación técnica publicada.
- Validar pago de administración y alistamiento del día antes de expedir (módulos 5 y 7; el legado los validaba).
- Comparendos: no bloquean (pendiente que el usuario confirme si cobro coactivo o embargo deben bloquear).
- Firma digital del gerente en el PDF (art. 8 permite firma digital).
- ~~Plantilla del contrato en PDF~~: hecho, con firma electrónica (ver `contratos-firma.md`). Falta el recibo de pago.

## Recibos de caja, estado de cuenta y paz y salvo (2026-11-03)
**Pedido del usuario:** que cada pago o abono de un contrato produzca un comprobante para entregar al cliente, tanto del abono como del pago total.

- **Recibo de caja por cada pago** (pestaña **Pagos y recibos** del contrato):
  - Lleva un número consecutivo por empresa con prefijo, por ejemplo `RC-0001`. El prefijo y el número inicial (para continuar el talonario de papel) se configuran en Integraciones → Recibos de caja.
  - Media carta horizontal.
  - Contenido:
    - recibimos de (cliente e identificación);
    - la suma de (en números y en letras);
    - por concepto de (abono, abono final o pago total del contrato No.);
    - forma de pago y referencia;
    - un cuadro con el valor del contrato, lo abonado antes, este pago y el saldo pendiente;
    - el sello «CONTRATO PAGADO EN SU TOTALIDAD» cuando el saldo queda en cero;
    - las firmas de quien recibe y del cliente, y un QR de verificación.
  - **Tipo de pago:** «pago total» si se pagó todo de una vez, «abono final» si el abono deja el saldo en cero y «abono» si queda saldo.
- **Al registrar el pago:** el recibo queda listo con el botón «Ver recibo» y se puede enviar en el mismo paso (casilla «Enviar el recibo al cliente», con el correo y el celular del cliente ya puestos).
- **Acciones por pago:** **Recibo** (descargar), **Enviar al cliente** y **Anular**.
  - **Enviar:** por correo va el PDF adjunto; por SMS, un enlace a `/verificar/recibo/{codigo}`, donde se ve y se descarga.
  - **Anular** exige un motivo. El pago deja de sumar al saldo y el recibo conserva su número, marcado ANULADO. El número nunca se reutiliza.
- **Estado de cuenta / paz y salvo** (botón en el encabezado del contrato):
  - Relaciona todos los recibos; los anulados aparecen tachados.
  - Muestra el total pagado y el saldo.
  - **Si el saldo es cero:** es la **constancia de pago total (paz y salvo)**, con el texto de certificación y la firma del representante legal.
  - Se descarga o se envía por correo.
- **Datos:**
  - `pagos_contrato` (columnas nuevas): `prefijo`, `numero` (no se repite por empresa), `codigo_verificacion`, `saldo_anterior`, `saldo_despues`, `anulado_at`, `anulado_por` y `motivo_anulacion`.
  - `empresas` (columnas nuevas): `recibo_prefijo` (RC) y `recibo_numero_inicial` (1).
  - La migración `2026_11_03_100001_create_recibos_pago.php` numera los pagos que ya existían, en orden de fecha.
- **Código:** `Support/Pdf/PdfRecibo.php` (vistas `pdf/recibo-pago.blade.php` y `pdf/estado-cuenta.blade.php`), `Http/Controllers/VerificarReciboController.php` (vista `recibo/verificar.blade.php`) y `Mail/CorreoReciboPago.php`.
- **Pruebas:** `tests/Feature/Operacion/RecibosPagoTest.php`, con 6 pruebas.
- **Nota:** el recibo de caja no reemplaza la factura electrónica de la DIAN; así lo dice al pie.

## Planeador de servicios (PESV, paso 15) (2026-11-03)
**Pedido del usuario:**
- Un planeador de servicios conforme a la Res. 40595 de 2022.
- Que desde él se generen extractos y que todo extracto expedido aparezca en él con su vehículo.

**Decisiones del usuario:**
- El plan es **libre**: puede ir antes o después del extracto.
- Exceder la jornada **solo avisa**.
- El seguimiento del conductor es **opcional**.
- El rutograma se toma **según el origen y el destino**.

**Qué pide la norma:**
- **Paso 15** (planificación de desplazamientos laborales): un procedimiento que contemple:
  - la antelación con que se planifica;
  - los requisitos para iniciar y finalizar el viaje;
  - horarios y tiempos de conducción;
  - velocidades seguras;
  - los documentos que se deben portar;
  - las paradas seguras;
  - los controles durante el recorrido;
  - medidas contra la fatiga, la velocidad y la distracción;
  - las rutas con puntos críticos.
- **Paso 8** (prevención de la fatiga): controlar la jornada, la conducción y el descanso, y planificar los viajes para evitar excesos.

### Qué se construyó
- **Rutogramas** (Operación → Rutogramas):
  - Datos de la ruta: origen y destino (opción «sirve también para el regreso»), vías, distancia, conducción estimada y velocidad máxima.
  - **Puntos en orden**: punto crítico, parada segura, punto de control o apoyo en emergencia, cada uno con referencia, velocidad e indicación al conductor.
  - Rutas alternas, recomendaciones y contactos de emergencia.
  - Se descarga en PDF.
  - `Ruta::sugerida` elige el rutograma de un servicio: el que coincide en origen y destino o, si es de ida y vuelta, el inverso.
- **Servicios** (Operación → Servicios planificados):
  - Fecha o rango de fechas, hora de salida y de llegada, origen y destino (con su rutograma), recorrido y pasajeros.
  - Todo lo demás es **opcional**: contrato, vehículo, hasta 3 conductores y la conducción estimada por día.
  - **Generar extracto:** abre «Expedir FUEC» con los datos del plan (`?servicio=`), y el FUEC queda enlazado al servicio.
  - **Todo FUEC expedido** (desde el planeador o desde FUEC) crea o enlaza su servicio (`PlanificarServicio::desdeFuec`), así que aparece en el planeador con su vehículo.
  - Con extracto, en el plan solo se cambian el rutograma, la conducción, los pasajeros y las observaciones.
  - Al anular el FUEC, el servicio queda cancelado.
  - La migración creó el servicio de los FUEC vigentes que ya existían.
- **Planeador** (Operación → Planeador):
  - Tablero semanal **por vehículo** o **por conductor**. En la vista por conductor muestra las horas de conducción de cada día, en rojo si superan el máximo.
  - Fila «Sin vehículo» o «Sin conductor».
  - Con «+» en cada celda se planifica un servicio para ese día y ese vehículo o conductor.
  - **Indicadores de la semana:**
    - servicios y servicios con extracto;
    - servicios sin vehículo o sin conductor;
    - % planificados con la antelación configurada;
    - % de planes leídos por el conductor;
    - servicios con avisos de jornada.
  - **Avisos:** servicios que salen en 48 h sin extracto y avisos de jornada.
- **Control de jornada** (`Support/Operacion/Jornada.php`), con la política de Integraciones → Planeador de servicios (por defecto 10 h al día, 4 h continuas y 8 h de descanso; la empresa debe poner los valores de su política). **Solo avisa** cuando:
  - la conducción del día del conductor supera el máximo;
  - hay conducción continua mayor que el máximo sin una parada segura en el rutograma;
  - el descanso entre la última llegada de un día y la primera salida del siguiente es menor que el mínimo;
  - el vehículo o el conductor tienen otro servicio planificado a la misma hora (el FUEC ya bloquea estos cruces).
- **Plan de viaje en PDF**, para llevar a bordo:
  - el servicio y el rutograma con sus puntos;
  - requisitos para iniciar, controles durante el recorrido (fatiga, velocidad, distracción) y requisitos para finalizar;
  - la constancia de lectura del conductor, con su firma registrada.
- **Portal del conductor:** cada tarjeta de servicio tiene «Plan de viaje y rutograma», donde el conductor:
  - ve los puntos del rutograma;
  - marca **«Leí el plan de viaje»**;
  - opcionalmente, **inicia el viaje** (km), **registra paradas**, **reporta novedades** y **finaliza** (km).
  - Si el celular lo permite, cada reporte guarda la ubicación.
  - Las novedades emiten el webhook `servicio.novedad` (n8n).
- **Permisos:**
  - `servicios`: ver, crear, editar y anular.
  - `rutas`: ver, crear, editar y eliminar.
  - El Auxiliar planifica y crea rutogramas; Gerencia solo ve.
- **Datos:**
  - tablas `rutas`, `servicios`, `servicio_conductor` y `eventos_servicio`;
  - en `empresas`: `jornada_max_horas_dia`, `jornada_max_horas_continuas`, `jornada_descanso_horas` y `planeacion_antelacion_horas`;
  - migración `2026_11_03_200001_create_planeador_servicios.php`.
- **Código:**
  - modelos `Ruta`, `Servicio` y `EventoServicio`; enums `EstadoServicio` y `TipoPuntoRuta`;
  - `Actions/Operacion/PlanificarServicio.php` (guardar, desdeFuec, cancelar y reportar);
  - `Pages/Planeador.php` y `Resources/{Rutas,Servicios}`;
  - `Support/Pdf/PdfPlanViaje.php`;
  - `Http/Controllers/Conductor/ViajeController.php`.
- **Pruebas:** `tests/Feature/Operacion/PlaneadorTest.php`, con 7 pruebas.

