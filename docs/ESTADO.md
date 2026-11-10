# Estado del proyecto – Rediseño COOTRASEC (multiempresa)

Última actualización: 2026-11-09

## Hecho
- [x] Relevamiento del legado (`legado/`), decisiones (`DECISIONES.md`) y arquitectura (`nuevo/ARQUITECTURA.md`).
- [x] Propuesta visual aprobada: estilo flonext; pantalla principal = tablero de gerencia.
- [x] **Módulo 1 – Configuración:** construido y con 22 pruebas en verde. Detalle en `nuevo/modulos/configuracion.md`.
- [x] Desplegado en Easypanel: https://prueba.geotracksolucionesapp.online (`/admin` plataforma, `/app` empresas).
- [x] **Módulo 2 – Flota:** propietarios, vehículos (hoja de vida), documentos con historial, estados con novedades y vencimientos. 32 pruebas en verde. Detalle en `nuevo/modulos/flota.md`; esquema y mapeo en `nuevo/datos.md`.
- [x] Corregido: los despliegues ya no borran los ajustes de roles de cada empresa.
- [x] **Módulo 3 – Conductores:** hoja de vida, seguridad social, comparendos, documentos con historial, estados con novedades, asignación a vehículos y vencimientos de conductores. 41 pruebas en verde. Detalle en `nuevo/modulos/conductores.md`.
- [x] **Módulo 4 – Operación:** clientes, contratos con pagos y saldo, expedición del FUEC (Res. 6652/2019) con revisión en vivo, PDF con QR y verificación pública, anulación. 50 pruebas en verde. Detalle en `nuevo/modulos/operacion.md`.
- [x] FUEC con el formato oficial (anverso y reverso) y sello Vigilado SuperTransporte.
- [x] **Contratos: plantilla editable y firma electrónica** (enlace + código por SMS con Onurix, correo de respaldo, certificado de evidencias, verificación pública). 61 pruebas en verde. Detalle en `nuevo/modulos/contratos-firma.md`.
- [x] **Módulo 5 – PESV y portal del conductor:** firma única del conductor, portal `/conductor` con tarjetas de servicio (Hacer preoperacional / Ver extracto, bloqueado hasta un preoperacional apto), ítems de chequeo configurables, inspección mensual, sin preoperacional hoy, PDF con firma y QR, informe mensual por vehículo. 75 pruebas en verde (SQLite y MySQL). Detalle en `nuevo/modulos/pesv.md`.
- [x] **FUEC sin cruces de horario:** el vehículo y los conductores no se asignan a dos servicios que se cruzan (horas del FUEC). Ver `nuevo/modulos/operacion.md`, regla 8.
- [x] **Módulo 6 – Mantenimiento:** planes de acción automáticos (fallas del preoperacional e inspecciones, rutinas vencidas) con evidencias y verificación; mantenimientos con evidencias (auxiliar, rol Mantenimiento y conductor desde el portal); plan preventivo por km y tiempo con semáforo; informe PDF. 94 pruebas en verde (SQLite y MySQL). Detalle en `nuevo/modulos/mantenimiento.md`.
- [x] **Módulo 6, segunda parte – Programa de mantenimiento:** plantillas (clase, marca y línea, o libres), plan personalizado por vehículo con punto de partida, programa anual con aprobación y trazabilidad (línea base, ejecución automática, reprogramación y cancelación con motivo, versiones), calendario, cronograma PDF, Excel para la Supertransporte y webhooks a n8n. 102 pruebas en verde (SQLite y MySQL). Detalle en `nuevo/modulos/mantenimiento.md`.
- [x] **Recibos de caja de los contratos:** recibo por cada pago (abono, abono final o pago total) con consecutivo y QR; envío por correo y SMS; anulación con motivo; estado de cuenta y paz y salvo. Detalle en `nuevo/modulos/operacion.md`.
- [x] **Planeador de servicios (PESV, paso 15):** tablero semanal por vehículo o conductor; rutogramas por origen y destino; servicios libres desde los que se genera el extracto (todo FUEC aparece en el planeador); avisos de jornada y fatiga; plan de viaje en PDF; seguimiento opcional en el portal. Detalle en `nuevo/modulos/operacion.md`.
- [x] **Módulo de gerencia:** tablero con filtros de periodo y oficina, informes en Excel, resumen en PDF y envío mensual automático (correo y webhook). Detalle en `nuevo/modulos/gerencia.md`.
- [x] **Colas y errores:** filas alta y baja, servicio scheduler, latidos y vigilante, páginas de error en español con referencia, registro y alerta de errores, y la pantalla Estado del sistema. Detalle en `nuevo/modulos/sistema.md`.
- [x] **Consultas de tránsito (comparendos y RUNT) con PlacApi:**
  - la plataforma habilita el módulo y fija los precios por empresa;
  - consumo por mes con Excel para facturar;
  - la empresa elige los tipos, el tope, las automáticas y los bloqueos (la licencia suspendida bloquea el FUEC);
  - consultas con el precio a la vista.

  Detalle en `nuevo/modulos/consultas-transito.md`.
- [x] **Módulos por empresa** (fase 0 de carga): cada empresa ve solo lo contratado. Detalle en `nuevo/modulos/modulos-empresa.md`.
- [x] **Carga, fase 1 – datos maestros del RNDC:** terceros, remolques, datos de carga del vehículo, registro por web service (listo, sin conectar), ficha PDF y bitácora. Detalle en `nuevo/modulos/carga.md`.
- [x] **Carga, fase 2 – remesas y manifiestos:** expedición con consecutivos propios, PDF del manifiesto con QR verificable, anulación con motivos del RNDC. Detalle en `nuevo/modulos/carga.md`.
- [x] **Carga, fase 3 – cumplidos:** cumplir remesas y manifiestos con tiempos reales y ajustes al pago; validaciones del manual v2.3; neto a pagar al titular. Detalle en `nuevo/modulos/carga.md`.
- [x] **Carga, fase 4 y 5:** anulaciones reportadas al RNDC (proceso 9 remesa y 32 manifiesto, con sus motivos y transbordo), contingencia del RNDC (comando `ruta:rndc-contingencia` que reencola lo pendiente si se pierde la cola), tiempos logísticos desde el celular del conductor, SICE-TAC automático, plazos de pago y rutas municipales. Falta solo la conexión real al web service (ver Pendiente). Detalle en `nuevo/modulos/carga.md`.
- [x] **SMS centralizado como servicio de la plataforma:** una sola cuenta Onurix que el superadministrador configura en `/admin`, activa y cobra por empresa; contador de consumo por empresa (segmentos, precio y costo) con pantalla para facturar. La empresa ya no instala credenciales por su cuenta. Detalle en `nuevo/modulos/sistema.md`.
- [x] **Informes de operación en Excel y PDF:** extracción de los informes del módulo Operación con la cabecera y el pie de página estándar del sistema (horizontal cuando hay muchas columnas); los preoperacionales incluyen el número de extracto (FUEC) y de contrato.
- [x] **Hoja de vida en PDF (vehículo y conductor):** por secciones que el usuario elige (datos, documentos, servicios, preoperacionales, conductores que lo condujeron…), con cabecera, pie y sin cortar información entre páginas.
- [x] **Acceso del panel de plataforma restringido:** a `/admin` solo entra el superadministrador; al crear una empresa, el administrador recibe sus credenciales por correo con una clave temporal y el sistema lo obliga a cambiarla en el primer ingreso.
- [x] **Módulo 7 – Pagos de administración:** cuota mensual por vehículo (valor por clase, ajustable por vehículo), generación automática el día 1 (o con botón manual desde el estado de cuenta), estado de cuenta mes a mes con mora, pago repartido de la cuota más vieja a la más nueva (FIFO) con recibo propio en PDF, y anulación que devuelve el abono. Módulo opcional que el superadministrador activa por empresa.
- [x] Dockerfile de producción (`Dockerfile`, `docker/`), con los pasos de arranque probados en modo producción; indicador de versión del build (`.build`/`.version`) para confirmar el despliegue.
- [x] **Cotizador de servicios (Operación):** cotizaciones de transporte especial con líneas Fecha/Ruta/Duración/Valor, descuento e IVA opcional; PDF con la marca de la empresa idéntico al formato aprobado (encabezado, tabla, total, bloques "incluye"/"equipo", validez con logos de certificación, pie), configurable por empresa (colores, textos, validez, pie, logos) en Configuración → Mi empresa → Cotizaciones; envío por correo al cliente con el PDF adjunto; y conversión de la cotización aceptada en contrato (de ahí sale el FUEC). Destinatario cliente o prospecto. Permiso `cotizaciones`, módulo Pasajeros.
- [x] **Centro de alertas:** bandeja unificada en el inicio (documentos de vehículos y conductores por vencer/vencidos y administración en mora, con conteo, color y enlace a su listado) y avisos automáticos por correo al propietario/conductor afectado — resumen semanal (lunes) y aviso puntual el día del vencimiento — más campana al equipo con permiso. Comando `ruta:alertas` (modos diario y semanal).
- [x] Suite completa en verde: 329 pruebas (SQLite y MySQL).
- [x] **Endurecimiento para producción (2026-11-09):** 2FA (TOTP) opcional en empresas y **obligatorio para el superadministrador**; **tablero del panel de plataforma** (resumen, estado del sistema, últimas empresas, altas por mes) con badges en el menú y ficha de empresa con resumen; **auditoría global** de plataforma; lista de usuarios de plataforma **solo administradores** por defecto; **firma del representante solo en el panel de la empresa**; avisos de vencimiento a **30/15/7 días**; **listado de pasajeros etapa 2** (enlace al cliente, anexo en el PDF del FUEC, importación Excel/CSV); correo de credenciales en cola; **suite de aislamiento multiempresa**; y el pie del menú lateral se oculta al colapsar.
- [x] **Respaldos automáticos a Cloudflare R2** (`spatie/laravel-backup`): BD + archivos subidos en ZIP cifrado, diarios, fuera del servidor, con retención y aviso de fallo. Falta configurar las llaves de R2 en Easypanel (ver Pendiente). Detalle en `docs/integraciones/respaldos-r2.md`.
- [x] **Monitoreo, alertas y diagnóstico (2026-11-10):** historial de ocurrencias por error (cualquier `REF-…` encuentra su error), chequeos de salud de la app y de los proveedores, endpoint `GET /salud` con token para monitores externos, panel de salud en «Estado del sistema» y avisos por campana, correo y **webhook**. Detalle en `docs/integraciones/monitoreo-y-alertas.md`. Falta poner `ALERTAS_WEBHOOK_URL`, `ALERTAS_WEBHOOK_SECRETO` y `SALUD_TOKEN` en Easypanel.
- [~] **Manual de la aplicación en el panel** (`/admin` → Manual, solo superadministrador): guía por módulos editable desde el panel (editor visual), índice + buscador y descarga en PDF. Armazón y módulos «Introducción» y «Operación» redactados; **pendiente redactar el resto de módulos** (Flota, Conductores, Contratos y firma, PESV/portal, Mantenimiento, Gerencia, Carga, Consultas de tránsito, Pagos de administración, Plataforma/Sistema, Integraciones, Respaldos, Seguridad).
- [x] Código en GitHub: `gestiondecalidadysst-alt/app_robert` (rama `main`). Es la fuente oficial desde el 2026-10-30; `solucionesappcontact-jpg/app_operation` queda como repositorio anterior y `Escritorio\APP` como copia local.

## Módulos que faltan por construir
- [ ] **Combustible y liquidación de vehículos:** consumo/tanqueo por vehículo y liquidación al propietario (servicios menos administración, combustible y descuentos). Se cruza con pagos de administración.
- [ ] **Planillas, tiquetes y caja:** recaudo diario del despacho (planilla por vehículo/ruta, tiquetes, cuadre de caja). El tablero de gerencia ya está listo para recibir estos números.

## Mejoras acordadas (backlog)
Próximas a construir (acordadas con el usuario el 2026-11-06):
- [ ] **Portal del propietario / afiliado:** acceso tipo portal (como el del conductor) para que el dueño vea el estado de cuenta de administración de sus vehículos, descargue recibos y paz y salvo, y vea los documentos y vencimientos de su vehículo. Incluye como parte del mismo trabajo: enviar la cuenta de cobro/recibo de administración al propietario (correo y SMS) y el estado de cuenta consolidado en PDF.
- [x] **Centro de alertas y avisos automáticos de vencimientos** (hecho el 2026-11-06, ver sección "Hecho").

Para después (sin fecha):
- [~] **PWA instalable para conductor y propietario** (por fases):
  - [x] **Fase 1 – instalable + marca (conductor):** service worker (`/conductor/sw.js`), registro en el layout, página de respaldo sin conexión (`/conductor/offline`), manifest e íconos, y aviso de "Instalar app" (diálogo nativo en Android/Chrome; instructivo Compartir→Agregar a inicio en iPhone). Hecho el 2026-11-06.
  - [x] **Fase 2 – notificaciones push (conductor):** Web Push nativo (aes128gcm + VAPID ES256, sin librerías ni extensiones extra), suscripción desde el portal ("Activar avisos"), tabla `suscripciones_push`, service worker con `push`/`notificationclick`, baja automática en 404/410. Comando `ruta:generar-vapid` para las llaves. Eventos que avisan al conductor (`App\Support\Push\NotificarConductor`): **nuevo extracto asignado** (al expedir el FUEC), **mantenimiento de su vehículo** (próximo/vencido, enganchado al aviso con dedup de `AvisosPrograma`), **preoperacional pendiente** del servicio de hoy y sus **documentos por vencer** (en `ruta:alertas`). Hecho el 2026-11-06. Pendiente de despliegue: poner `VAPID_PUBLIC` y `VAPID_PRIVATE_PEM_B64` en Easypanel y verificar en un celular real (la entrega solo se puede probar contra el servicio de push en producción). iOS: push desde iOS 16.4, solo si la app está agregada a la pantalla de inicio.
  - [ ] **Fase 3 – preoperacional offline** con cola de envío (lo más delicado; requiere pruebas en celular real).
  - [ ] Falta la PWA del portal del propietario (va junto con ese módulo).
- [ ] **WhatsApp como canal** (además de SMS y correo) para firma, recibos, cuentas de cobro y avisos; requiere proveedor (API de WhatsApp Business o gateway).
- [ ] **Administración – extras:** bloqueo opcional del FUEC por mora (interruptor por empresa) y recargos/intereses de mora configurables.
- [ ] **PESV:** registro de accidentes/incidentes e indicadores de accidentalidad y cumplimiento para reportes a Supertransporte/Mintransporte.
- [ ] **Facturación electrónica DIAN** de servicios/contratos.

## Pendiente / bloqueos
### Despliegue y configuración (lado del usuario)
- [ ] Redesplegar en Easypanel (web, **worker** y **scheduler**) para publicar todo lo construido; las migraciones corren solas al arrancar. El worker es imprescindible: sin él no salen recordatorios de firma, webhooks, contingencia del RNDC ni los informes mensuales.
- [ ] Montar el volumen de `storage/app` en Easypanel (firmas, evidencias, logos) — sin él se pierden en cada despliegue.
- [ ] Crear el servicio `scheduler` (`CONTAINER_ROLE=scheduler`), poner `SCHEDULE_EN_WORKER=false` en el worker y, si se quiere, `ALERTAS_CORREO`.
- [ ] Correo con Brevo: poner `MAIL_PASSWORD` (clave SMTP de Brevo) y las variables de correo en Easypanel; probar con `ruta:probar-correo`. Verificar el DNS del dominio (SPF/DKIM/DMARC) para que el correo no caiga en spam.
- [ ] SMS: crear la cuenta de Onurix y ponerla en `/admin` → Plataforma → Mensajes de texto; luego activar el SMS por empresa (la URL del API no está verificada con documentación oficial).
- [ ] Notificaciones push del conductor: generar las llaves con `php artisan ruta:generar-vapid`, poner `VAPID_PUBLIC` y `VAPID_PRIVATE_PEM_B64` en Easypanel, redesplegar y probar "Activar avisos" en un celular.
- [ ] Consultas de tránsito: poner la API key de PlacApi y los precios en `/admin/consultas-transito`, habilitar el módulo por empresa y revisar la primera respuesta real.
- [ ] Activar por empresa los módulos opcionales que vayan a usar (carga, consultas de tránsito, **pagos de administración**).
- [ ] En «Mi empresa»: completar código territorial, resolución y año de habilitación (sin ellos no se expide FUEC); subir la firma del representante, la cédula "expedida en", el sitio web y la imagen del pie (certificaciones).
- [ ] **Respaldos a Cloudflare R2:** crear el bucket R2 privado y un API Token; poner `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_ENDPOINT`, `R2_BUCKET`, `R2_REGION=auto`, `BACKUP_ARCHIVE_PASSWORD` y `BACKUP_NOTIFICATION_EMAIL` en Easypanel; probar con `php artisan backup:run` y `backup:list`. Guardar la contraseña del ZIP aparte. Requiere el servicio scheduler corriendo. Detalle en `docs/integraciones/respaldos-r2.md`.

### Carga – conexión real al RNDC
- [ ] Poner el usuario del RNDC de la empresa y confirmar el formato del web service en la primera conexión real. Todo el código (registro, remesas, manifiestos, cumplidos, anulaciones, tiempos) ya está; falta conectarlo y probar contra el ambiente real.

### Legal / externos
- [ ] Confirmar la finalidad del tratamiento de datos (cláusula 19 del contrato) y revisar con el abogado el texto y la cláusula de firma electrónica.
- [ ] FUEC electrónico por RUNT (Decreto 1048 de 2026): pendiente de reglamentación del Ministerio.

### PESV
- [ ] Revisar con el usuario los ítems por defecto y cuáles son críticos (Seguridad vial → Ítems de chequeo); dar acceso al portal a los conductores.

## Escalar (alta concurrencia: cientos/miles de usuarios activos)
Guía completa y cómo actuar: **`docs/nuevo/ESCALABILIDAD.md`**.
Hecho en la app: la imagen ya trae la extensión `phpredis` y el `.env.example` documenta el salto a Redis; las tareas pesadas van a cola (worker); hay índices por `empresa_id`.
Pendiente (configuración/infra en Easypanel, por orden de impacto):
- [x] **Redis para sesión, caché y cola:** en producción desde el 10/11/2026 (`/salud` → «Caché / Redis»). Descarga la base de datos y es requisito para varias réplicas.
- [ ] **Varias réplicas del servicio web** detrás del balanceador de Easypanel (app sin estado: sesión en Redis y archivos en volumen compartido o S3). Con `FILESYSTEM_DISK=local` cada réplica tendría su disco: usar el MISMO volumen en todas, o `FILESYSTEM_DISK=s3`.
- [ ] **Servidor web de mayor concurrencia:** Apache mod_php gasta mucha memoria por proceso; evaluar PHP-FPM + Nginx o Laravel Octane/FrankenPHP.
- [ ] **Base de datos** (el techo real): instancia dedicada, afinar (buffer pool, conexiones), y réplicas de lectura para informes si hace falta.
- [ ] **Más workers** en paralelo para absorber picos de cola; **medir** antes (tiempos de respuesta, consultas lentas) para dimensionar con datos.

## Siguiente paso
1. Redesplegar (web, worker y scheduler) con el volumen de `storage/app`, completar Mi empresa e Integraciones (Brevo, Onurix), y probar correo y SMS.
2. Configurar los respaldos: crear el bucket de Cloudflare R2 y sus llaves, ponerlas en Easypanel y verificar el primer `backup:run`.
2. Siguiente módulo sugerido: **Combustible y liquidación de vehículos**. Después: planillas, tiquetes y caja.
3. En cada vehículo: definir el valor de administración (por clase o ajuste) y generar las cuotas del mes; registrar el punto de partida del plan de mantenimiento y aprobar el programa anual; configurar el webhook de n8n en Integraciones.
4. Crear los rutogramas de las rutas frecuentes y poner la política de jornada de la empresa en Integraciones → Planeador de servicios.
5. Conectar el RNDC real (usuario de la empresa) y validar el web service.

## Deuda consciente de seguridad (decidido el 10/11/2026)

Varios secretos de producción se expusieron durante el trabajo del 10/11/2026 y **no todos se
rotaron**. Lo que sí se rotó: contraseña de la base (`DB_PASSWORD`), clave SMTP de Brevo,
`BACKUP_ARCHIVE_PASSWORD`, `SALUD_TOKEN`, `ALERTAS_WEBHOOK_SECRETO` y llaves VAPID.

Pendiente, por decisión del usuario (se deja para más adelante):

- [ ] **Contraseña de `root` de MySQL.** Riesgo acotado mientras el servicio MySQL no exponga un
  puerto público: solo se llega desde la red interna de Docker. Comprobar ese puerto antes de
  bajarle prioridad. El botón de cambio de Easypanel **no sirve**: tiene guardado el usuario
  `mysql`, que no existe (el real es `robert`); hay que hacerlo por SQL.
- [ ] **`APP_KEY`.** Cifra en la base las credenciales RNDC y Onurix, las llaves de proveedores de
  tránsito, los secretos de webhook, los tokens de firma de contratos y los secretos de 2FA.
  Rotarla obliga a volver a escribir todo eso a mano y **mata los enlaces de firma pendientes**,
  que afectan a terceros. Hoy el costo es bajo porque casi no hay datos reales; crece con cada
  empresa que entre. Antes de decidir, correr el inventario (cuenta cuántas filas cifradas hay) y
  avisar a las empresas si hay contratos esperando firma.

