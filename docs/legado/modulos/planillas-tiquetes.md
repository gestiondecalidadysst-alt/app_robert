# Planillas, tiquetes y caja

## Tiquetes / Planillas — `?page=planillas_index` (`planillas_php/server_planillas.php`, `planillas_php/server_clientes.php`)
Operación tipo terminal (pasajeros individuales) por despacho/oficina.
- **Planilla de despacho** (un viaje de un vehículo): placa, tipo de vehículo, hora salida (hora/min/AM-PM), conductor, origen, destino, fecha, valor planilla.
  - **Deducciones**: administración, seguridad social.
  - Valida vigencias del vehículo y licencia del conductor (ocultos) antes de despachar.
- **Enturnar vehículos**: asignar turno de salida a la placa (`idplacasturnos`).
- **Tiquete** (por pasajero): cédula (busca cliente o crea nuevo), nombres, apellidos, celular, destino, número de puesto, valor tiquete. Muestra total de puestos ocupados vs capacidad.
- Tabla planillas: Expedida | Placa | Vehículo | Trayecto | Conductor | Oficina | Ocupantes.
- Buscador de clientes (Cédula | Nombres | Apellidos | Celular).

## Cuadre de caja — `?page=cuadre_caja_index` (`cuadre_caja_php/procesar_busquedad.php`)
- Filtro: asesor (usuario), rango de fechas -> informe de planillas y otros; exportar PDF.

## Planillas administrar — `?page=planillas_admin_index` (`planillas_admin/procesar_busquedad.php`)
- Filtro por placa (o todas) y fechas.

## Historial de tiquetes — `?page=buscar_tiquetes_index` (`buscar_tiquetes/cargar_datos.php`)
- Buscar por NIT/CC cliente, nombres, apellidos o fechas de viaje.

## Cuadre de caja por oficinas — `?page=cuadres_por_oficinas` (`cuadre_caja_oficina/cargar_datos_desp.php`)
- Filtro: oficina/despacho y fechas.

## Liquidar vehículos — `?page=liquidar_vehiculos` (`liquidar_vehiculos_php/cargar_datos_desp.php`)
- Filtro: placa (o todas) y fechas -> liquidación de lo producido por el vehículo menos deducciones; exportar PDF.

## Para el rediseño
- Venta de tiquetes con mapa de asientos visual y control de sobrecupo.
- Caja por sesión de usuario (apertura/cierre) con arqueo.
- Liquidación al propietario: ingresos (planillas, contratos) − deducciones (administración, seguridad social, combustible, mantenimientos) con PDF.
