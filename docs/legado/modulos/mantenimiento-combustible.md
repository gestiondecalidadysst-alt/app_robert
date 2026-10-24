# Mantenimientos y combustible

## Mantenimientos — `?page=mantenimiento_index` (`mantenimientos/server_extractos.php`)
- Tipo: PREVENTIVO / CORRECTIVO. Acción: CAMBIO / AJUSTE / REVISIÓN / REPARACIÓN.
- Campos: placa, fecha, actividad realizada, problema presentado, repuesto (nombre/ref.), centro automotriz, documento y nombre del responsable, precio, número de factura, adjunto de factura.
- Tabla: Vehículo | Mantenimiento | Acción | Fecha | Actividad.

## Informe de mantenimientos — `?page=informe_mantenimiento_index` (`informe_mantenimiento/buscarVehiculos.php`)
- Filtro: fechas y placa (o todas); generar PDF.

## Recibos de combustible — `?page=combustibles_index` (`combustibles_php/serverDatos.php`)
- Campos: número de planilla/control, placa (oculta, se deduce de la planilla), origen, destino, combustible (tarifa), galones por km.
- Tabla: Planilla No. | Placas | Recorrido | Combustible | Galones.

## Para el rediseño
- Plan de mantenimiento preventivo por km o tiempo con alertas automáticas (hoy el cronograma es un PDF).
- Órdenes de trabajo con estados (abierta, en taller, cerrada) que cambian el estado del vehículo a TALLER.
- Combustible: tanqueos reales (fecha, galones, valor, km) y rendimiento km/galón.
