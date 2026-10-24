# Informes de operación y formatos

| Informe | page= | Contenido | Endpoint |
|---|---|---|---|
| Pagos del día | `gerente_php/pagos_diario_index` | Contratante | Val. contrato | Total abonos | Estado/vencido | Estado | `gerente_php/server_pagos_dia.php` |
| Estado de contratos (viajes) | `gerente_php/viajes_index` | Final | Contratante | Val. contrato | Estado/vencido | Estado | `gerente_php/server_contratos.php` |
| Operación flota | `operacion_flota/index` | Tarjeta por vehículo: placa, operador, dirección recogida, hora recogida, recorrido (servicios programados) | `gerente_php/server_contratos.php` |
| Informe inspección | `inspeccion_info/index` | Ver inspecciones.md | |
| Informe inspección PDF | `informe_inspecion_pdf/index` | Ver inspecciones.md | |

FORMATOS (menú) solo enlaza a: Ficha técnica, Programa mantenimiento y Vehículos (tarjeta de propiedad) — ya documentados en vehiculos.md.

## Para el rediseño
- Dashboard de gerencia con indicadores: vehículos operando/suspendidos/taller, extractos del día, cartera, cumplimiento del preoperacional, vencimientos.
- Operación de flota como tablero del día (calendario/timeline por vehículo).
