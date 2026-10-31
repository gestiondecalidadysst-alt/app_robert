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
- `app/Actions/Operacion/RegistrarPago.php`: no admite pagos a contratos anulados ni mayores que el saldo.
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
- Plantilla del contrato en PDF y recibo de pago (el legado los tenía).
