# Contratos: plantilla editable y firma electrónica

Estado: **construido y probado** (11 pruebas propias; 61 en total en verde). Fecha: 2026-10-31.
Amplía el Módulo 4 – Operación (`operacion.md`).

## Qué resuelve
El contrato de prestación de servicios (formato GPS-PR01-FT03 v02 de COOTRASEC) se diligenciaba a mano sobre una plantilla, con errores: texto montado sobre el pie de página, datos faltantes ("$ CERO PESOS"), firma escaneada del gerente pegada en la plantilla. Ahora:
1. La **plantilla** se edita en la app, con **versiones**, y los datos se insertan como **variables** (no a mano).
2. **Emitir** congela el texto con los datos del contrato. Si falta un dato, no deja emitir y dice cuál.
3. **Enviar para firma**: enlace único por **SMS (Onurix)** y por correo, con confirmación de los datos antes de enviar.
4. El cliente firma desde el celular con un **código de un solo uso** (SMS; correo como alternativa) y su firma dibujada.
5. El PDF firmado lleva un **certificado de firma electrónica** con las evidencias, su huella SHA-256 y un QR de verificación.

## Flujo
**Contrato → Vista previa → Emitir → Enviar para firma → (cliente) abre → pide código → firma → Firmado.**
Estados del documento: Emitido, Enviado para firma, Visto por el cliente, Firmado, Enlace vencido (calculado), Anulado.
- Un contrato emitido **no se edita**: se anula el documento (con motivo) y se emite de nuevo.
- Anular el contrato anula también su documento.
- Envío **manual con confirmación** por defecto; opción "Enviar para firma al emitir" en Integraciones (apagada por defecto).

## Pantallas
| Dónde | Qué hace | Permiso |
|---|---|---|
| `contratos/{id}` → pestaña **Documento y firma** | Vista previa (PDF con marca "VISTA PREVIA"), Emitir (muestra lo que falta), Enviar/Reenviar (modal con nombre, documento, celular y correo del firmante), Descargar PDF, Copiar enlace, Historial (eventos y mensajes), Anular. | `ver/crear/anular_documentos_contrato` |
| `plantilla-contrato` | Versiones (vigente y reemplazadas), ver cada versión con las variables resaltadas, **Editar plantilla (nueva versión)** con editor y lista de variables; "Volver al texto base". | `ver/crear_plantillas_contrato` |
| `integraciones` | Onurix (activar, Client ID, clave cifrada, límite mensual, SMS del mes, **Consultar saldo**, **Enviar SMS de prueba**) y firma (días de vigencia del enlace, días para recordatorio, envío automático). | `ver/editar_integraciones` (Gerencia no lo ve) |
| Mi empresa | Nuevos: sitio web, cédula del representante "expedida en", **firma escaneada del representante** (privada), imagen del pie (certificaciones y sellos). | `editar_empresa` |
| `/f/{token}` (pública) | Lee el contrato, pide el código (SMS o correo), escribe el código, dibuja la firma, acepta y firma. Después, descarga la copia firmada. | — |
| `/verificar/contrato/{codigo}` (pública, QR) | Estado del contrato y **comprobación de un PDF** (se sube y se compara su huella; no se guarda). | — |
| Campana de notificaciones | Aviso a quien emitió: contrato firmado / enlace vencido sin firma. | — |

## Archivos clave
### Plantilla y redacción
- `app/Support/Contratos/PlantillaBase.php`: texto base (de GPS-PR01-FT03 v02) con variables y las correcciones de abajo. Lo recibe cada empresa nueva (`CrearEmpresa::crearPlantillaContrato`) y las existentes al desplegar (`PlantillasContratoSeeder`, en `docker/entrypoint.sh`).
- `app/Support/Contratos/VariablesContrato.php`: 40 variables (contrato, contratante, empresa, documento) con etiqueta, si son obligatorias y su texto por defecto. Calcula valor en letras, fechas en español, horas y horas de disponibilidad, "C.C. No. … expedida en …" o "NIT …-DV", forma de pago.
- `app/Support/Contratos/RedactorContrato.php`: diligencia la plantilla (merge tags del RichEditor de Filament) y devuelve lo que falta.
- `app/Support/NumeroEnLetras.php`: "DIECIOCHO MILLONES DE PESOS M/CTE" (apócopes: veintiún, un millón, "de pesos" en millones exactos).
- `app/Models/PlantillaContrato.php`, `app/Actions/Contratos/GuardarPlantillaContrato.php` (versión mayor que la vigente; rechaza variables desconocidas).

### Documento y firma
- `app/Models/DocumentoContrato.php` (+ `EventoDocumentoContrato`, `CodigoFirma`, `EnvioMensaje`), `app/Enums/EstadoFirma.php`.
- Acciones `app/Actions/Contratos/`: `EmitirDocumentoContrato` (copia logo, pie y firma del representante a la carpeta del documento; huella SHA-256 del contenido), `EnviarParaFirma` (mismo enlace si sigue vigente; SMS de un solo mensaje sin tildes), `SolicitarCodigoFirma`, `FirmarDocumentoContrato`, `AnularDocumentoContrato`.
- `app/Support/FirmaManuscrita.php`: valida la firma dibujada (PNG, tamaño y que no esté vacía).
- `app/Support/Pdf/PdfContrato.php` + `resources/views/pdf/contrato.blade.php`: encabezado y pie fijos con márgenes reservados (no se montan sobre el texto), "Página X / Y", bloque de firmas, certificado de evidencias. `app/Support/Pdf/Imagenes.php` (QR e imágenes embebidas, compartido con el FUEC).
- Públicas: `app/Http/Controllers/FirmaContratoController.php`, `VerificarContratoController.php`, vistas `resources/views/firma/*`, `resources/views/contrato/verificar.blade.php`. Límites por IP con claves separadas (`AppServiceProvider`: firma 60/min, código 8/min, firmar 20/min, verificar archivo 10/min).
- Pantallas: `app/Filament/App/Resources/Contratos/RelationManagers/DocumentosRelationManager.php`, `app/Filament/App/Resources/PlantillasContrato/*`, `app/Filament/App/Pages/Integraciones.php`.
- Seguimiento: `app/Console/Commands/SeguimientoFirmas.php` (`ruta:firmas`, diario 9:10 a. m. con `schedule:work` del worker): un recordatorio con el mismo enlace y aviso de enlace vencido.

### Mensajería
- `app/Services/Mensajeria/OnurixSms.php`: `POST {ONURIX_URL}/send-sms` (client, key, phone, sms, country_code) y `GET /balance`. URL por defecto `https://www.onurix.com/api/v1` (configurable con `ONURIX_URL`).
- `app/Services/Mensajeria/Mensajero.php`: envía y registra cada mensaje (destino enmascarado, sin contenido). Código de verificación: envío inmediato; enlaces, recordatorios y copias: en cola con reintentos (`app/Jobs/EnviarSms.php`, `EnviarCorreo.php`, **cifrados** en la cola).
- `app/Mail/CorreoFirmaContrato.php` + `resources/views/correo/firma-contrato.blade.php`: enlace, recordatorio, código y contrato firmado (PDF adjunto).

### Base de datos
`database/migrations/2026_10_31_100001_create_contratos_firma.php`: columnas nuevas en `empresas` (credenciales **cifradas** con APP_KEY), `plantillas_contrato`, `documentos_contrato`, `codigos_firma`, `eventos_documento_contrato`, `envios_mensaje`, `notifications`.

## Seguridad
- Enlace: 22 caracteres aleatorios; en la base solo su huella (búsqueda) y una copia cifrada (para recordatorios con el mismo enlace). Vence a los N días (7 por defecto); el recordatorio no amplía el plazo.
- Código: 6 dígitos, solo su huella HMAC; un solo uso; vence en 10 minutos; bloqueo a los 5 intentos; máximo 3 códigos cada 15 minutos (evita gastar saldo); un solo código válido a la vez.
- Credenciales de Onurix cifradas, ocultas en pantalla ("••••a91f") y fuera de la auditoría (`$noAuditar` en el modelo; el trait `Auditable` lo respeta). La auditoría registra quién las cambió.
- Firma del representante en disco privado; solo se estampa en documentos emitidos (copia en la carpeta del documento).
- Páginas públicas con `noindex` y `Referrer-Policy: no-referrer` (el enlace no se filtra a otros sitios).
- PDF firmado guardado una sola vez; su huella SHA-256 se registra y se puede comprobar en la verificación pública.

## Correcciones aplicadas al texto base (revisar con el abogado)
1. Datos fijos de COOTRASEC (NIT, representante, cédula, dirección, correo, teléfonos, web, ciudad) → variables de "Mi empresa".
2. Cláusula 2: "pajeros" → "pasajeros", con la cantidad del contrato.
3. Cláusula 3: se quitó la frase incompleta "Todo incumplimiento del horario establecido."; las "09 horas" se calculan con las horas del contrato.
4. Cláusula 5: la forma de pago va en el texto ("de contado, antes de iniciar el servicio" o "a crédito, a más tardar el …") en vez de "Pagaderos antes de iniciar el servicio", que contradecía el crédito.
5. Cláusula 6: se quitó la obligación 7 ("Lazar improperios…", que es una prohibición y ya está en la 7) y la 11 (repetida de la 10).
6. Cláusula 7: se quitó la prohibición 9 (repetida de la 8); "Lazar" → "Lanzar"; "3 año" → "tres años"; "no autorizado" → "no haya autorizado".
7. Cláusula 12: "La Compañía" y "el proveedor" (restos de otra plantilla) → redacción neutra entre las partes.
8. Cláusulas 17, 18 y 20: "esta cooperativa" / "La Compañía" → EL CONTRATISTA / EL CONTRATANTE; se quitó "CLÁUSULA DÉCIMA SÉPTIMA" del título de la 20.
9. Cláusula 19: la finalidad ("xxxxxxxx" en el original) se completó con una finalidad general — **el usuario debe confirmarla**.
10. Nueva cláusula 23: aceptación de la firma electrónica (Ley 527 de 1999, Decreto 2364 de 2012).
11. Sin cambiar (decisión del usuario): cláusula 10 menciona "legislación vigente para las entidades públicas"; el depósito de $100.000 sigue escrito en el texto.

## Pendientes / ideas
- **Onurix exige autorizar la IP del servidor** (error "Ip's no permitidas"): en Integraciones, «Ver IP del servidor» muestra la IP pública de salida (consulta api.ipify.org, guardada 1 hora) y el error de envío la incluye. Esa IP se registra en la cuenta de Onurix; si Easypanel/el proveedor cambia la IP, hay que actualizarla.
- **Probar Onurix con credenciales reales**: la URL y los parámetros salen de la especificación pública del API (no de documentación oficial verificada). Usar "Enviar SMS de prueba"; si falla, ajustar `ONURIX_URL`.
- Configurar **SMTP** en Easypanel (sin él los correos van al log): enlace, código por correo y copia firmada.
- El worker debe correr (`CONTAINER_ROLE=worker`) para enlaces, recordatorios y copias por correo.
- WhatsApp (n8n + Evolution API) como canal adicional: el envío está centralizado en `Mensajero`.
- Otrosí (modificación de un contrato firmado) y firma del representante en el mismo flujo electrónico.
- Firma digital certificada de la empresa (sello) como mejora posterior.
