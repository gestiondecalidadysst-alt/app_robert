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
- `contrato_id`, `consecutivo` (por contrato, único), `numero` (21 dígitos), `vehiculo_id`, `fecha_inicio`, `fecha_fin`, `origen_municipio_id`, `destino_municipio_id`, `recorrido`, `empresa_convenio_id`, `datos` (JSON: copia de empresa, contrato, vehículo, conductores, origen, destino y convenio al expedir), `codigo_verificacion` (único, QR), `oficina_id`, `user_id`, anulación (`anulado_at`, `motivo_anulacion`, `anulado_por`).
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

