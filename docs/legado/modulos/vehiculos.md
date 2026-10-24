# Propietarios y Vehículos

## Propietarios — `?page=propietarios_index` (`propietario/serverDatos.php`)
- Campos: identificación, nombres, apellidos, fecha nacimiento, género, fecha ingreso, número contacto, e-mail, dirección, ciudad (municipio).
- Tabla: Identificación | Nombres | Apellidos | F. nacimiento | Contacto | E-mail | Dirección | Ciudad.
- ~51 propietarios (incluye la propia cooperativa como propietaria).

## Vehículos — `?page=vehiculos_index` (`vehiculos/registros_crud.php` POST multipart, `vehiculos/server_process.php`)
- Datos de tarjeta de propiedad: foto, placa, marca, línea (depende de marca), modelo, cilindraje, color, servicio, clase, carrocería, combustible, capacidad kg/pasajeros, No. motor, VIN, No. chasis, propietario, puertas, fecha expedición, organismo de tránsito.
- Ficha técnica: puertas eléctricas, potencia HP, No. ejes, sistema de dirección, sistemas y tipo de frenos delanteros/traseros, llantas delanteras/traseras, suspensión delantera/trasera.
- Tabla: Placa | Marca | Línea | Modelo | Clase | Combustible | Cap. | Puertas | acciones (ver ficha / editar).
- ~85 vehículos en operación (127 en total incluyendo inactivos).

## Control de documentos — `?page=vehiculo_documen_index` (`vehiculos_documentos/server_process.php`)
- Contadores arriba: registrados / suspendidos / en operación.
- Formulario por vehículo: vencimiento técnico-mecánica, vencimiento preventiva (bimensual), vencimiento póliza RC-RCE, vencimiento SOAT, número y vencimiento tarjeta de operación, tipo de vinculación (CONVENIO EMPRESARIAL / VINCULACION DE FLOTA), empresa afiliada, fecha convenio, número interno.
- Adjuntar PDF del documento (`file`, `ruta`, `nom_file`).
- Tabla: PLACA | PÓLIZA | SOAT | TARJETA OPERACIÓN | TÉCNICO-MECÁNICA | TÉCNICO-BIMESTRAL | ¿OPERANDO? | ESTADO | PDF.
- Regla: solo se guarda la **fecha vigente**; no hay historial de renovaciones.

## Novedades — `?page=vehiculo_novedades_index` (`vehiculos_novedades/server_process.php`)
- Mismo formulario de documentos + modal de novedad: fecha, tipo (SUSPENDER SERVICIO / DESVINCULACION / TALLERES), observación.
- Modal "Vehículo suspendido": fecha de reactivación.
- Estados del vehículo: OPERANDO, SUSPENDIDO, TALLER (desvinculado = fuera de flota).

## Ficha técnica — `?page=vehiculos_ficha` (`vehiculos/server_fichas.php`)
- Listado de vehículos -> genera ficha técnica + historial de mantenimientos (formato imprimible).

## Programa de mantenimiento — `?page=mantenimiento_progra/index`
- Listado + botón "Cronograma Insp. Preventiva" -> PDF `mantenimiento_progra/cronograma_pdf.php`.

## Reglas de negocio
- Un vehículo solo puede usarse en extractos/planillas/contratos si está OPERANDO y con documentos vigentes (se verifica al expedir extracto, ver extractos.md).
- La clase del vehículo define el valor de administración mensual.

## Para el rediseño
- Tabla `documentos_vehiculo` con historial (tipo, número, emisor, fecha expedición, vencimiento, archivo) en vez de columnas fijas.
- Estado del vehículo como máquina de estados con historial de novedades.
- Fusionar Vehículos + Ficha técnica + Documentos en una vista de detalle por vehículo (pestañas).
