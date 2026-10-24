# Operadores (conductores)

## Registro — `?page=operarios_index` (`operarios/registros_crud.php` POST multipart)
- Campos visibles: foto, No. documento, nombres, apellidos, género, celular.
- Sección "Datos básicos y de identificación" (el modal tiene más campos de los que se extrajeron; probablemente EPS, ARL, nivel académico, que son catálogos existentes) -> **verificar en la app**.
- Tabla: Documento | Nombres | Apellidos | Celular | Foto.
- ~273 conductores en el selector de extractos.

## Documentos — `?page=operarios_documentos` (`operarios_documentos/server_process.php`)
- Por conductor: vencimiento licencia, categoría (CUARTA, QUINTA, SEXTA, ESPECIAL C1/C2/C3, NO APLICA), servicio (PARTICULAR/PUBLICO), vencimiento salud (seguridad social), organismo de tránsito expedidor.
- Comparendos: SI/NO, cantidad, estado (ACUERDO DE PAGO, COBRO COACTIVO, EMBARGO, ACTUALIZACION PAGO, NO APLICA).
- Tabla: Documento | Nombres | Apellidos | Licencia vence | Categoría | Estado.

## Novedades — `?page=operador_novedades_index` (`operador_novedades/server_process.php`)
- Tipos: RETIRO, SUSPENDER SERVICIO; observación; fecha de reactivación.
- Tabla: Documento | Nombres | Apellidos | Licencia vence | En servicio | Suspendido.

## Reglas
- En el extracto se toman cédula, vencimiento de licencia y salud del conductor 1 y 2 para validar vigencia.

## Para el rediseño
- Igual que vehículos: documentos con historial y adjuntos; estado con historial.
- Consulta de comparendos (SIMIT) como dato con fecha de última verificación.
- Relación conductor–vehículo (asignación) explícita; hoy solo existe implícita en extractos/planillas.
