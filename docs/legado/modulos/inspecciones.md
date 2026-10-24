# Preoperacional e inspecciones (PESV)

## Alistamiento diario (checklist) — `?page=alistamiento_diario_index` (`alistamiento/server_chklist.php`, `alistamiento/obtener_horas.php`)
- Lo diligencia el operador/conductor antes de operar.
- Campos: placa, operador del vehículo, km inicial, recorrido, observación (obligatoria). Toma datos de vigencias del vehículo (ocultos).
- Checklist de **19 ítems** (de la tabla parámetros "Alistamiento Día"), cada uno checkbox cumple/no cumple:
  aceite motor; refrigerante; líquido de frenos; líquido dirección; luces; llantas y rines; espejos y vidrios; cinturones; extintor; kit de carretera; limpiaparabrisas; carrocería; testigos del tablero; SOAT/matrícula; documentación completa; botiquín; frenos de mano y pedal; vidrios; espejos.
- Se guarda total de ítems seleccionados/no seleccionados.
- **Firma digital**: tabla "OPERADOR | PLACA | FECHA | FIRMAR!" -> modal con lienzo de firma (base64).

## No realizadas — `?page=preoperacion_info/index` (`preoperacion_info/procesar_busquedad.php`)
- Listado de vehículos que **no** hicieron el preoperacional del día.

## Alistamiento día (admin) — `?page=alistamiento_dia_admin_index` (`alistamiento/server_vehiculos_chklist.php`)
- Vehículos chequeados: OPERADOR | PLACA | OBS | FECHA Y HORA | ¿ESTÁ FIRMADO?; permite editar el checklist.

## Inspección mensual PESV — `?page=inspeccion_mes_index` (`inspeccion_mes/server_vehiculos_chklist.php`)
- Campos: conductor, placa, km, fecha de inspección.
- ~30 ítems con respuesta **S / N / N/A** y observación por ítem (basura, cabina, llantas, presión, repuesto, extintor y su carga/manómetro, luces, direccionales, pito, dirección, frenos, combustible, motor, líquidos, limpiaparabrisas, espejos, condición general, SOAT, técnico-mecánica, licencia, tarjeta de operación, pólizas, botiquín, equipo de carretera…).
- Los ítems parecen fijos en código (no usan la tabla "Inspección parámetros") -> verificar.

## Informes
- Informe alistamiento diario — `?page=inspeccion_info/index`: por placa y rango de fechas.
- Informe preoperacional PDF — `?page=informe_inspecion_pdf/index`: por placa y fechas, imprimir.

## Para el rediseño
- App móvil / PWA para el conductor: checklist con fotos de novedades, firma, geolocalización y hora.
- Un ítem "no cumple" crítico bloquea la operación del día y genera orden de mantenimiento correctivo.
- Ambos checklists (diario y mensual) configurables con el mismo motor de formularios (ítem, tipo de respuesta, crítico sí/no).
