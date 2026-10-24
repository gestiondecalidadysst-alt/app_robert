# Contratos

## Elaborar contratos eventuales — `?page=contrato_eventual/index` (`contrato_eventual/server_process.php`)
Modal en secciones:
- **Duración y ruta**: fecha inicial, fecha vence, hora inicio, hora fin, recorrido.
- **Vehículo**: placa (puede incluir varios carros: `cant_carros`, `otras_placa`), clase, capacidad, cantidad de pasajeros; valida vigencia de póliza.
- **Contratante**: cédula/NIT, expedida en, razón social, dirección, teléfono (si no existe se crea cliente nuevo: `clientenuevo`).
- **Responsable del contrato**: cédula, nombres, teléfono, dirección.
- **Valor y forma de pago**: valor pagado, valor contrato, forma de pago (CONTADO/CREDITO), pagaderos, tipo y objeto (1. Estudiantes, 2. Empresarial, 3. Turistas, …), objeto (texto).
- Tabla: Placa | F. inicia | F. termina | Razón social | Documento | Teléfono | Contrato (PDF) | Recibo (PDF).
- Consecutivo de contrato (`vnum_siguientes`), usuario y oficina.

## Bitácora contratos — `?page=contratos/index` (`contratos/cargar_datos.php`)
- Búsqueda por número, NIT/CC, razón social, fechas de vencimiento, estado vencido, placa. Modal de edición.

## Administrar — `?page=contrato_event_admin/index` (`contrato_event_admin/cargar_datos.php`)
- Búsqueda (número, NIT, razón social, fechas de expedición) + mismo formulario que Elaborar (duplicado).

## Abonos / Pagos — `?page=contratos_pagos/index` (`contratos_pagos/cargar_datos.php`)
- Registrar pago: contrato (selector de ~2.397 contratos), fecha pago, valor, método (EFECTIVO, TRANSFERENCIAS, TARJETA DE CRÉDITO), observaciones.
- Tabla: Número contrato | Contratante | Val. contrato | Fecha pago | Valor abono | Método | Total pagos | Resta.

## Reglas
- Saldo = valor contrato − suma de abonos. Contratos a crédito generan cartera (ver Informe pagos día).
- Los extractos se emiten contra un contrato vigente.

## Para el rediseño
- Distinguir **cliente** (contratante) como entidad propia reutilizable.
- Contrato con varios vehículos y varios servicios (viajes) asociados; cada servicio genera su FUEC.
- Cuenta por cobrar con estado (al día / vencido) calculado.
