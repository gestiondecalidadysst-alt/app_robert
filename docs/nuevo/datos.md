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

