# Estado del proyecto – Rediseño COOTRASEC (multiempresa)

Última actualización: 2026-11-01

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
- [ ] **Módulo de carga (RNDC):** fases 1 a 4 según el plan. Falta el manual técnico de interoperabilidad del RNDC y las respuestas del usuario: usuario del RNDC, tipo de cliente, tipos de manifiesto, tiempos logísticos y cobro.
- [x] Dockerfile de producción (`Dockerfile`, `docker/`), con los pasos de arranque probados en modo producción.
- [x] Código en GitHub: `gestiondecalidadysst-alt/app_robert` (rama `main`). Es la fuente oficial desde el 2026-10-30; `solucionesappcontact-jpg/app_operation` queda como repositorio anterior y `Escritorio\APP` como copia local.

## Pendiente / bloqueos
- [ ] Redesplegar en Easypanel para publicar los módulos 2, 3 y 4 (las migraciones corren solas al arrancar). En «Mi empresa» completar código territorial, resolución y año de habilitación: sin ellos no se expide FUEC.
- [ ] Confirmar con el usuario los campos extra del registro de operadores en la app actual.
- [ ] Consultas de tránsito:
  - poner la API key de PlacApi y los precios en `/admin/consultas-transito`;
  - habilitar el módulo en cada empresa;
  - revisar la primera respuesta real en el historial.
- [ ] Configurar el correo (SMTP) en Easypanel: recuperación de contraseñas y correos de firma (enlace, código, copia firmada).
- [ ] Crear la cuenta de Onurix, poner sus credenciales en Configuración → Integraciones y probar con "Enviar SMS de prueba" (la URL del API no está verificada con documentación oficial).
- [ ] Confirmar la finalidad del tratamiento de datos (cláusula 19 del contrato) y revisar con el abogado las correcciones del texto y la cláusula de firma electrónica.
- [ ] En «Mi empresa»: subir la firma escaneada del representante, la cédula "expedida en", el sitio web y la imagen del pie (certificaciones).
- [ ] FUEC electrónico por RUNT (Decreto 1048 de 2026): pendiente de reglamentación del Ministerio.

- [ ] PESV: revisar con el usuario los ítems por defecto y cuáles son críticos (Seguridad vial → Ítems de chequeo); dar acceso al portal a los conductores.

## Siguiente paso
1. Redesplegar (web y worker), completar Mi empresa e Integraciones, firmar un contrato de prueba y hacer un preoperacional desde un celular.
2. Siguiente módulo sugerido: **Módulo 7 – Pagos de administración** (cuota mensual por vehículo, estado de cuenta; la app anterior bloqueaba el FUEC con administración atrasada). Después: combustible y liquidación de vehículos; planillas, tiquetes y caja (el tablero de gerencia ya está construido).
3. En cada vehículo: registrar el punto de partida del plan (o «arrancan hoy»), generar el programa anual y aprobarlo; configurar el webhook de n8n en Integraciones.
4. Crear los rutogramas de las rutas frecuentes y poner la política de jornada de la empresa en Integraciones → Planeador de servicios.
5. En Easypanel: crear el servicio `scheduler` (`CONTAINER_ROLE=scheduler`), poner `SCHEDULE_EN_WORKER=false` en el worker y, si se quiere, `ALERTAS_CORREO`.
6. Montar el volumen de `storage/app` en Easypanel (firmas, evidencias, logos) — sin él se pierden en cada despliegue.
