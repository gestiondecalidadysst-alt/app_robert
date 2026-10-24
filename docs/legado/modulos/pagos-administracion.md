# Pago de administración (cuota del vehículo afiliado)

- URL: `?page=vehiculos_pagos/index` (`vehiculos_pagos/cargar_datos.php`).
- Campos: placa en operación, clase (auto), valor a pagar (auto, según **clase** del vehículo), facturador/afiliado (propietario), fecha de pago, valor pagado, observaciones.
- Tabla: Fecha pago | Placa | Clase | Valor a pagar | Valor pagado | Facturador/afiliado | Observaciones | Opciones (recibo).

## Reglas
- El valor mensual viene de `Parámetros > Clases > Valor administración`.
- La fecha del último pago alimenta la validación del extracto (`fechaPagAdmin`, `diasPgAdmin`): vehículo con administración atrasada no puede expedir extracto (confirmar umbral).

## Para el rediseño
- Generar cuentas de cobro mensuales automáticamente y registrar pagos contra ellas (estado de cuenta por vehículo/propietario).
