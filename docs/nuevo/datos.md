# Esquema nuevo y mapeo legado → nuevo

Todas las tablas de negocio llevan `empresa_id` (multiempresa). Catálogos nacionales: ver `ARQUITECTURA.md`.

## Módulo 2 – Flota (`2026_10_30_100001_create_flota.php`)

### `propietarios`
- `tipo_identificacion_id`, `documento` (único por empresa, sin puntos), `nombres` (o razón social), `apellidos`, `fecha_nacimiento`, `genero` (F/M/O), `fecha_ingreso`, `telefono`, `email`, `direccion`, `municipio_id`, `activo`, *soft delete*.
- Legado `Propietario`: cedulas→documento, fechanace→fecha_nacimiento, idgeneros→genero, fechaingreso→fecha_ingreso, numcontato→telefono, emails→email, ciudad→municipio_id.

### `vehiculos`
- `placa` (única por empresa), `numero_interno` (único por empresa), `propietario_id`.
- Tarjeta de propiedad: `marca_id`, `linea_id`, `modelo`, `clase_vehiculo_id`, `carroceria_id`, `combustible_id`, `tipo_servicio_id`, `color`, `cilindraje`, `capacidad_pasajeros`, `capacidad_kg`, `puertas`, `numero_motor`, `vin`, `numero_chasis`, `fecha_matricula`, `organismo_transito_id` (→ municipios), `foto_path`.
- `ficha_tecnica` (JSON): potencia_hp, numero_ejes, puertas_electricas, sistema_direccion, frenos/llantas/suspensión delanteros y traseros.
- Vinculación: `tipo_vinculacion` (convenio_empresarial | vinculacion_flota), `empresa_convenio_id`, `fecha_vinculacion`.
- `estado` (operando | suspendido | taller | desvinculado), `estado_desde`, `observaciones`, *soft delete*.
- Legado `Vehículo`: idmarcas→marca_id, idlineas→linea_id, idservicios→tipo_servicio_id, idclaseve→clase_vehiculo_id, idcarroceria→carroceria_id, idscombustibles→combustible_id, capacidadkg→capacidad_kg, nmotor→numero_motor, numvin→vin, numchasis→numero_chasis, idspropietarios→propietario_id, fecha_expide→fecha_matricula, idstransitos→organismo_transito_id, campos de ficha técnica→ficha_tecnica.
- Legado `Documentos vehículo` (columnas de vinculación): idconvenios→tipo_vinculacion, idempresa_afi→empresa_convenio_id, fecha_convenio→fecha_vinculacion, num_interno→numero_interno.

### `documentos_vehiculo` (historial: una fila por documento expedido)
- `vehiculo_id`, `tipo` (soat | tecnomecanica | revision_preventiva | poliza_rcc | poliza_rce | tarjeta_operacion), `numero`, `aseguradora_id`, `entidad`, `fecha_expedicion`, `fecha_vencimiento`, `archivo_path`, `archivo_nombre`, `observacion`, *soft delete*.
- Legado `Documentos vehículo` (1:1, solo fecha vigente) → una fila por columna con fecha: mecanica_vence→tecnomecanica, preventiva_vence→revision_preventiva, soat_vence→soat, tarjeta_numero/tarjeta_vence→tarjeta_operacion, poliza_vence→**dos filas** (poliza_rcc y poliza_rce, mismo vencimiento) porque el legado las tenía juntas. El PDF del legado se adjunta a la fila que corresponda.

### `novedades_vehiculo`
- `vehiculo_id`, `tipo` (suspension | taller | desvinculacion | reactivacion | vinculacion), `fecha`, `estado_anterior`, `estado_nuevo`, `fecha_reactivacion`, `observacion`, `user_id`.
- Legado `Novedad vehículo`: idsnovedad SUSPENDER SERVICIO→suspension, TALLERES→taller, DESVINCULACION→desvinculacion; fecha_activar→fecha_reactivacion.

## Módulo 3 – Conductores (`2026_10_30_200001_create_conductores.php`)

### `conductores`
- `tipo_identificacion_id`, `documento` (único por empresa), `nombres`, `apellidos`, `fecha_nacimiento`, `genero`, `grupo_sanguineo`, `telefono`, `email`, `direccion`, `municipio_id`, `foto_path`, `nivel_academico_id`, `oficina_id`, `fecha_ingreso`.
- Seguridad social: `eps_id`, `arl_id`, `fondo_pensiones` (texto).
- Emergencia: `contacto_emergencia`, `telefono_emergencia`.
- Comparendos: `comparendos_estado` (sin_comparendos | pendiente | acuerdo_pago | cobro_coactivo | embargo), `comparendos_cantidad`, `comparendos_consultado_el`.
- `estado` (activo | suspendido | retirado), `estado_desde`, `user_id` (portal del conductor, módulo 9), `observaciones`, *soft delete*.
- Legado `Operador`: documento→documento, idgeneros→genero, numcel→telefono, foto→foto_path. `Documentos operador`: comparendo SI/NO + estado comparendo→comparendos_estado (NO→sin_comparendos), can_comparedo→comparendos_cantidad.

### `documentos_conductor` (historial)
- `conductor_id`, `tipo` (licencia | seguridad_social | examen_medico | manejo_defensivo | contrato), `numero`, `categoria_licencia_id` (solo licencia), `entidad`, `fecha_expedicion`, `fecha_vencimiento`, `archivo_path`, `archivo_nombre`, `observacion`, *soft delete*.
- Legado `Documentos operador`: licencia_vence + idcategorias + nombre_organismo→fila tipo licencia (categoría y entidad); vence_salud→fila tipo seguridad_social. `idservicios` (particular/público) no se migra: lo define la categoría (C = público).

### `novedades_conductor`
- Igual que `novedades_vehiculo`, con `tipo` suspension | retiro | reactivacion | reintegro.
- Legado `Novedad operador`: RETIRO→retiro, SUSPENDER SERVICIO→suspension, fecha_activar→fecha_reactivacion.

### `asignaciones_vehiculo` (nueva, no existía en el legado)
- `conductor_id`, `vehiculo_id`, `fecha_desde`, `fecha_hasta` (vacía = vigente), `principal`, `observacion`.

## Módulo 4 – Operación (`2026_10_30_300001_create_operacion.php`)

### `clientes`
- `tipo_identificacion_id`, `documento` (único por empresa), `dv` (solo NIT, calculado), `nombre`, `expedicion_municipio_id`, `direccion`, `municipio_id`, `telefono`, `email`, `contacto`, `activo`, *soft delete*.
- Legado `Contratante`: cc_contratante→documento, cc_expedida→expedicion_municipio_id, nom_contratante→nombre, direc→direccion, cell→telefono.

### `contratos`
- `numero` (consecutivo por empresa, único), `cliente_id`, `objeto` (escolar | empresarial | salud | turismo | grupo_especifico), `descripcion_objeto`, `fecha_inicio`, `fecha_fin`, `hora_inicio`, `hora_fin`, `origen_municipio_id`, `destino_municipio_id`, `recorrido`, `disposicion_total`, `zonas_disposicion`, `cantidad_vehiculos`, `cantidad_pasajeros`.
- Responsable: `responsable_nombre`, `responsable_documento`, `responsable_telefono`, `responsable_direccion`.
- Valor: `valor`, `forma_pago` (contado | credito), `fecha_limite_pago`. `oficina_id`, `user_id`, `observaciones`.
- Anulación: `anulado_at`, `motivo_anulacion`, `anulado_por` (no se borran).
- Legado `Contrato eventual`: consecutivo→numero, finicial/fvence→fechas, hora_inicio/hora_fin, recoridos→recorrido, cant_pasajeros→cantidad_pasajeros, contratante→cliente_id, responsable→responsable_*, valcontrato→valor, id_pagos CONTADO/CREDITO→forma_pago, IdContratos/objetoc→objeto/descripcion_objeto (mapear tipos 1. Estudiantes→escolar, 2. Empresarial→empresarial, 3. Turistas→turismo…), usuario→user_id, oficina→oficina_id. `mivalor` (valor pagado al crear) → un registro en `pagos_contrato`. Placas del contrato: no se guardan en el contrato; viven en cada FUEC.

### `pagos_contrato`
- `contrato_id`, `fecha`, `valor`, `metodo` (efectivo | transferencia | tarjeta | otro), `referencia`, `observacion`, `user_id`.
- Legado `Pago de contrato`: fecha_pago→fecha, valorPagado→valor, codMetodo (EFECTIVO, TRANSFERENCIAS, TARJETA DE CRÉDITO)→metodo, obspago→observacion.

### `fuecs`
- `contrato_id`, `consecutivo` (por contrato, único), `numero` (21 dígitos), `vehiculo_id`, `fecha_inicio`, `fecha_fin`, `hora_inicio` y `hora_fin` (franja diaria; vacías = todo el día), `origen_municipio_id`, `destino_municipio_id`, `recorrido`, `empresa_convenio_id`, `datos` (JSON: copia de empresa, contrato, vehículo, conductores, origen, destino y convenio al expedir), `codigo_verificacion` (único, QR), `oficina_id`, `user_id`, anulación (`anulado_at`, `motivo_anulacion`, `anulado_por`).
- `fuec_conductor`: `fuec_id`, `conductor_id`, `orden` (1–3).
- Legado `Extracto`: número control→id interno; número extracto→`numero`; contrato, fecha_inicio, fecha_fin, placa→vehiculo_id, idorigen/iddestino→municipios, recorridos→recorrido, idconductor1/idconductor2→fuec_conductor (orden 1 y 2), usuario, oficina. Al migrar, los extractos históricos conservan su número original y se reconstruye `datos` con lo disponible; nombre_pdf no se migra (el PDF se genera).

## Contratos: plantilla y firma (`2026_10_31_100001_create_contratos_firma.php`)
- `empresas` (nuevas): `representante_expedicion`, `sitio_web`, `firma_representante_path` (privada), `pie_documentos_path`, `sms_habilitado`, `onurix_cliente` y `onurix_clave` (cifradas), `sms_limite_mensual`, `firma_envio_automatico`, `firma_dias_enlace` (7), `firma_dias_recordatorio` (2).
- `plantillas_contrato`: `version` (única por empresa; vigente = mayor), `codigo_formato`, `titulo`, `contenido` (HTML con merge tags), `notas_cambio`, `user_id`.
- `documentos_contrato`: `contrato_id`, `plantilla_contrato_id`, `version_plantilla`, `codigo_formato`, `contenido` (congelado), `datos` (valores, encabezado, archivos), `huella_contenido`, `firma_contratista_path`, `estado`, `codigo_verificacion`, `token_hash`, `token_cifrado`, `token_expira_at`, firmante (`nombre`, `documento`, `celular`, `email`), evidencias (`enviado_at`, `visto_at`, `firmado_at`, `firma_path`, `firma_ip`, `firma_user_agent`, `firma_canal_codigo`, `firma_destino_codigo`), `pdf_firmado_path`, `huella_pdf`, `recordatorio_enviado_at`, `vencimiento_avisado_at`, anulación.
- `codigos_firma`: `canal`, `destino` (enmascarado), `codigo_hash` (HMAC), `expira_at`, `intentos`, `usado_at`, `ip`.
- `eventos_documento_contrato`: `evento`, `ip`, `user_agent`, `detalle`, `user_id`, `created_at` (solo se agregan).
- `envios_mensaje`: `tipo`, `canal`, `destino` (enmascarado), `estado` (pendiente | enviado | fallido | omitido), `proveedor`, `referencia_proveedor`, `error`, `intentos`, `enviado_at`. No guarda el contenido.
- `notifications`: notificaciones del panel (Laravel).
- Legado: el contrato era un documento diligenciado a mano (plantilla Word/PDF); no hay datos que migrar a estas tablas.

## Módulo 5 – PESV (`2026_11_01_100001_create_pesv.php`)
- `users`: `email` pasa a ser opcional, porque los conductores entran con su documento; se agrega `debe_cambiar_clave`.
- `conductores` (columnas nuevas): `firma_path` (disco privado) y `firma_actualizada_at`.
- `items_chequeo` (una fila por ítem de la lista de cada empresa):
  - `tipo`: preoperacional o mensual.
  - `grupo` y `descripcion`.
  - `critico`: si falla, el vehículo queda NO APTO.
  - `orden` y `activo`.
- `inspecciones` (preoperacionales e inspecciones mensuales):
  - **Datos de la inspección:** `tipo`, `vehiculo_id`, `conductor_id`, `fuec_id`, `fecha`, `kilometraje` y `resultado` (apto o no_apto).
  - **Copias al registrar:**
    - `respuestas` (JSON): cada ítem con su grupo, descripción, si es crítico, la respuesta (si, no o na) y la observación.
    - `datos` (JSON): empresa, vehículo, conductor con su licencia, FUEC, quién la registró y si fue el conductor.
  - **Evidencia y verificación:** `observaciones`, `firma_path` (copia de la firma), `codigo_verificacion`, `ip`, `user_agent`, `latitud`, `longitud` y `user_id`.
- Correspondencia con la app anterior:

| App anterior | Sistema nuevo |
|---|---|
| Ítems del alistamiento diario (tabla parámetros «Alistamiento Día») y de la inspección mensual | `items_chequeo` |
| `Alistamiento diario`, cada checklist | `inspecciones` de tipo preoperacional: un ítem marcado → `si`, sin marcar → `no` |
| Kilometraje inicial | `kilometraje` |
| Observación | `observaciones` |
| Firma en base64 | Archivo de firma de la inspección |
| `Inspección mensual`: S, N y N/A | `si`, `no` y `na` |

  Las inspecciones de la app anterior no tienen ítems críticos marcados: al migrarlas, su resultado se toma como «apto» salvo que la observación indique lo contrario. Hay que revisarlo con el usuario.

## Módulo 6 – Mantenimiento (`2026_11_01_300001_create_mantenimiento.php`)
- `rutinas_mantenimiento`: `nombre`, `clase_vehiculo_id` (vacía = todas), `vehiculo_id` (propia del vehículo), `intervalo_km`, `intervalo_meses`, `descripcion`, `orden`, `activo`.
- `planes_accion`:
  - **Origen:** `vehiculo_id`, `origen` (preoperacional | inspeccion | preventivo | manual), `inspeccion_id`, `rutina_mantenimiento_id`.
  - **Contenido:**
    - `prioridad` (alta | media | baja) y `titulo`.
    - `hallazgos` (JSON). Cada hallazgo tiene `descripcion`, `critico`, `observacion`, `fecha` y `veces`.
    - `accion`, `responsable` y `fecha_limite`.
  - **Seguimiento:** `estado` (abierto | en_ejecucion | por_verificar | cerrado | rechazado), `reincidencias`, `observacion_cierre`, `cerrado_at`, `cerrado_por` y `user_id`.
- `mantenimientos`:
  - **Datos del trabajo:** `vehiculo_id`, `fecha`, `kilometraje`, `tipo` (preventivo | correctivo), `accion` (cambio | ajuste | revision | reparacion), `actividad`, `problema` y `repuestos`.
  - **Taller y costo:** `taller`, `responsable_documento`, `responsable_nombre`, `valor` y `factura_numero`.
  - **Vínculos y registro:** `plan_accion_id`, `conductor_id` (si lo registró el conductor) y `user_id`.
  - **Anulación:** `anulado_at`, `motivo_anulacion` y `anulado_por`.
- `mantenimiento_rutina`: tabla que relaciona cada mantenimiento con las rutinas que cumplió.
- `evidencias`: tabla polimórfica (`adjuntable`: mantenimiento o plan de acción) con `path` (disco privado), `nombre`, `mime`, `tamano`, `descripcion` y `user_id`.
- Correspondencia con la app anterior (`Mantenimientos`):

| App anterior | Sistema nuevo |
|---|---|
| PREVENTIVO / CORRECTIVO | `tipo` |
| CAMBIO / AJUSTE / REVISIÓN / REPARACIÓN | `accion` |
| Actividad | `actividad` |
| Problema | `problema` |
| Repuesto | `repuestos` |
| Centro automotriz | `taller` |
| Documento y nombre del responsable | `responsable_*` |
| Precio | `valor` |
| Número de factura | `factura_numero` |
| Adjunto de la factura | Una fila en `evidencias` |

## Módulo 6 – Programa de mantenimiento (`2026_11_02_100001_create_programa_mantenimiento.php`)
- `plantillas_mantenimiento`:
  - `nombre`.
  - `criterio`: clase | marca_linea | libre.
  - `clase_vehiculo_id`, `marca_id` y `linea_id`.
  - `descripcion`, `por_defecto` y `activo`.
- `rutinas_mantenimiento.plantilla_mantenimiento_id`: la plantilla a la que pertenece la rutina. Si no tiene, `vehiculo_id` indica que es del plan personalizado de un vehículo. `clase_vehiculo_id` ya no se usa.
- `vehiculos` (columnas nuevas):
  - `plantilla_mantenimiento_id`: la plantilla asignada, o la base del plan personalizado.
  - `plan_personalizado`.
- `mantenimientos.registro_inicial`: marca el punto de partida del plan, que no cuenta como mantenimiento real.
- `empresas` (columnas nuevas):
  - `km_diarios_defecto` (150) y `mantenimiento_dias_aviso` (7).
  - `webhook_activo`.
  - `webhook_url` y `webhook_secreto`, guardados cifrados.
  - `webhook_eventos` (JSON).
- `programas_mantenimiento`:
  - `anio` y `version`; estos dos con la empresa no se pueden repetir.
  - `estado`: borrador | aprobado | reemplazado.
  - `codigo_formato`, `km_diarios_defecto` y `observaciones`.
  - `elaborado_por` / `elaborado_at` y `aprobado_por` / `aprobado_at`.
- `actividades_mantenimiento`:
  - **Qué se programó:**
    - `programa_mantenimiento_id`, `vehiculo_id` y `rutina_mantenimiento_id`.
    - `rutina_nombre` y `frecuencia`, que son copias.
  - **Fechas:**
    - `fecha_original` y `km_original`: la línea base.
    - `fecha_programada` y `km_programado`: la fecha vigente.
  - **Ejecución:**
    - `estado`: programada | ejecutada | cancelada. «Vencida» y «próxima» no se guardan: se calculan por la fecha.
    - `mantenimiento_id`, `fecha_ejecucion` y `km_ejecucion`.
  - **Trazabilidad y avisos:**
    - `incorporada`, `motivo` e `historial` (JSON: fecha, tipo, detalle y usuario).
    - `avisado_proximo_at` y `avisado_vencido_at`.
- `webhook_entregas`: `evento`, `payload`, `estado` (pendiente | enviado | fallido), `codigo_respuesta`, `error`, `intentos` y `enviado_at`.

## Recibos de caja (`2026_11_03_100001_create_recibos_pago.php`)
- `pagos_contrato`:
  - `prefijo` y `numero` (con la empresa, no se pueden repetir).
  - `codigo_verificacion`: único, para el QR.
  - `saldo_anterior` y `saldo_despues`.
  - `anulado_at`, `anulado_por` y `motivo_anulacion`.
  - Saldo del contrato = valor − pagos no anulados.
- `empresas`: `recibo_prefijo` (por defecto RC) y `recibo_numero_inicial` (por defecto 1).

## Planeador de servicios (`2026_11_03_200001_create_planeador_servicios.php`)
- `rutas`:
  - `nombre`, `origen_municipio_id`, `destino_municipio_id`, `ida_y_vuelta` y `recorrido`.
  - `distancia_km`, `duracion_min` y `velocidad_maxima`.
  - `puntos` (JSON: tipo critico|parada|control|emergencia, nombre, referencia, velocidad e indicación).
  - `rutas_alternas`, `recomendaciones`, `contactos_emergencia` y `activo`.
- `servicios`:
  - **Enlaces:** `contrato_id`, `fuec_id` (único), `ruta_id` y `vehiculo_id`.
  - **Cuándo y dónde:** `fecha_inicio`, `fecha_fin`, `hora_salida`, `hora_llegada`, origen, destino y `recorrido`.
  - **Plan:** `conduccion_min` (por día), `pasajeros` y `observaciones`.
  - **Estado:** planificado|con_extracto|en_curso|finalizado|cancelado, con `motivo_cancelacion`.
  - **Seguimiento:** `leido_at` y `leido_por` (conductor), `iniciado_at` y `finalizado_at`, `km_inicial` y `km_final`.
  - `user_id`.
- `servicio_conductor`: (`servicio_id`, `conductor_id`, `orden`).
- `eventos_servicio`: `servicio_id`, `conductor_id`, `user_id`, `tipo` (leido|inicio|parada|novedad|fin|cancelado), `nota`, `kilometraje`, `latitud` y `longitud`.
- `empresas`: `jornada_max_horas_dia` (10), `jornada_max_horas_continuas` (4), `jornada_descanso_horas` (8) y `planeacion_antelacion_horas` (24).


## Consultas de tránsito (`2026_11_04_100001_create_consultas_transito.php`)
- **`proveedor_transito`** guarda una sola fila, la de la plataforma, con:
  - `proveedor`, `url` y `api_key` (cifrada);
  - `activo`;
  - `costo_credito` (COP);
  - `creditos` (json por tipo);
  - `precios` (json por tipo: precio de venta).
- **`empresas`** (nuevas columnas):
  - las que decide el superadministrador: `transito_habilitado` y `transito_precios` (precios especiales);
  - las que decide la empresa: `transito_tipos`, `transito_auto`, `transito_auto_tipos`, `transito_frecuencia_dias` (30 por defecto), `transito_tope_mensual`, `transito_bloquear_licencia` (encendido por defecto), `transito_bloquear_comparendos` y `transito_bloquear_desde`.
- **`conductores` y `vehiculos`:**
  - `transito_auto`, que indica si el registro entra en las automáticas;
  - `transito_resumen` (json), con el último resultado de comparendos, licencia y RUNT.
- **`consultas_transito`** guarda una fila por consulta:
  - empresa, tipo, sujeto (morph), referencia (cédula o placa), origen (`manual` o `automatica`) y usuario;
  - `estado` (`ok`, `sin_datos` o `error`), `creditos`, `cobrada`, `precio` (al cliente), `costo` (del proveedor) y `desde_cache`;
  - respuesta (json) y error.
- **`comparendos_transito`** guarda cada comparendo de un sujeto:
  - número, fecha, código, infracción, valor, estado (`pendiente`, `acuerdo`, `pagada` u `otro`), tipo, organismo y placa;
  - `primera_vez_at`, `ultima_vez_at` y `ya_no_aparece_at`;
  - es único por empresa, sujeto y número.
