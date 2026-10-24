# Extractos de contrato (FUEC)

El **extracto** es el Formato Único de Extracto de Contrato que todo vehículo de servicio especial debe portar por viaje. Es el documento central de la operación.

## Generar — `?page=extractos_php/index` (`extractos_php/server_extractos.php`)
Modal en secciones:
1. **Contratante**: selector de contratante/contrato (~78), NIT/CC, número de contrato (autocompletados).
2. **Vigencia y tipo**: fecha inicio, fecha vence, tipo de contrato (CONVENIO, CONSORCIO, UNION TEMPORAL, …), tiempo de conducción (horas), "con quién".
3. **Vehículo**: placa (85 en operación) -> autocompleta vencimientos SOAT, póliza y tipo de vehículo.
4. **Origen/destino/recorrido**: municipio salida, municipio llegada, hora salida, dirección de recogida, ruta o recorrido (texto).
5. **Conductores**: operador 1 (obligatorio) y operador 2 (opcional); trae cédula y vencimiento de licencia.
- Campos ocultos usados para validar antes de expedir: días restantes de póliza, SOAT, técnico-mecánica, tarjeta de operación, pago de administración, checklist (alistamiento), contrato, bimensual; salud y contrato de cada conductor; estado taller/operación/despacho del vehículo.
- Campos ocultos de "protocolo PESV" incluidos en el formato: parada segura, velocidad segura, control de recorrido, cursos del conductor, factores de riesgo, requisitos del viaje.
- Consecutivo: `vnum_siguientes` (siguiente número), se guarda nombre de PDF (`nombre_pdf`). Se registra usuario y oficina que expide.
- Tabla: Extracto No. | Expedido | Contratante | Vehículo | Conductor | Oficina | acciones (PDF).

### Reglas de negocio (inferidas, **confirmar en código/JS externo**)
- No se expide extracto si el vehículo tiene vencido: SOAT, póliza, técnico-mecánica, bimensual, tarjeta de operación, pago de administración, o no hizo alistamiento del día; o si está en taller/suspendido.
- No se expide si el conductor tiene licencia o salud vencida.
- No se expide si el contrato está vencido.
- La lógica está en JS externo (no inline) -> revisar el código fuente para confirmar umbrales.

## Administración — `?page=extractos_admin/index`: **devuelve 404** (roto).

## Informe expedidos — `?page=informe_extractos_index` (`informe_extractos/procesar_busquedad.php`)
- Filtros: usuario que expidió (o todos), rango de fechas.

## Modificar consecutivo — `?page=extractoModificarNum/index` (`extractoModificarNum/cargar_datos.php`)
- Buscar por número de control, placa, número de extracto o fechas; cambiar el número de extracto (nuevo número). Implica que el número de control (interno) y el número de extracto (legal) son distintos.

## Programador — `?page=programador/index` (`programador/buscar.php`, `programador/placas.php`)
- Búsqueda por fechas, NIT contratante, número de contrato o placa.
- Resultado: Extracto | Contrato | Contratante | NIT | Placa | Origen | Destino | Conductor | Cédula | F. inicia | F. vence | Días | Estado | Valor | Pago contrato | Recorridos | Alistamiento. Exporta Excel y PDF.

## Programador final — `?page=programador_final/index`
- Misma búsqueda + botón "Nuevo extracto" que abre el mismo formulario de Generar (duplicado). Endpoints `programador_final/buscar_html.php`, `programador_final/placas.php`.

## Para el rediseño
- Un único flujo "Programación de servicios": contrato -> servicio/viaje -> extracto, con validación en servidor y mensaje claro de qué documento bloquea.
- Numeración del FUEC según norma (consecutivo por empresa/año) generada en servidor y no editable salvo con auditoría.
- PDF del FUEC con QR de verificación.
- Selectores con búsqueda remota (municipios, conductores, contratantes).
