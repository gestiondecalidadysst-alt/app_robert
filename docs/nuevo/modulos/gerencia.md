# Módulo de gerencia: tablero e informes

**Estado:** construido y probado el 2026-11-03.
**Pedido del usuario:** un tablero e informes para gerencia, **sin** la hoja de indicadores del PESV (paso 20).
**Regla:** todo se calcula con lo que ya se registra; no se digita nada nuevo.

## Pantallas
| Dónde | Qué hace | Permiso |
|---|---|---|
| Gerencia → **Tablero** (`/app/{empresa}/gerencia`) | Indicadores con filtro de periodo (hoy, semana, este mes, mes anterior, año o rango) y de oficina. Cada número lleva al listado de donde sale. Botón **Resumen en PDF**. | `ver_gerencia` |
| Gerencia → **Informes** | Excel por rango y oficina, y el resumen en PDF. | `ver_gerencia` |

**Quién lo ve:** el permiso nuevo `gerencia` (ver) lo tienen el rol Gerencia y el Administrador; el Auxiliar no. En el Inicio aparecen los accesos «Tablero de gerencia» y «Planeador».

## Indicadores (`App\Support\Gerencia\Indicadores`)
- **Flota y conductores (a hoy):**
  - vehículos operando, suspendidos y en taller;
  - vehículos sin poder operar por documentos;
  - documentos de vehículos vencidos y por vencer;
  - conductores activos, con documentos vencidos o por vencer.
- **Operación (en el periodo):**
  - extractos expedidos y anulados, y contratos nuevos;
  - servicios del planeador: con extracto y sin vehículo o conductor;
  - **uso de la flota**: vehículos con servicio sobre vehículos operando;
  - servicios que salen en 48 h sin extracto;
  - principales clientes por valor contratado.
- **Comercial y cartera de contratos:**
  - contratado en el periodo, por fecha de elaboración del contrato;
  - recaudado en el periodo y en el día, con los recibos no anulados;
  - **cartera a hoy por edades**: sin vencer, 1–30, 31–60, 61–90 y más de 90 días, contados desde la fecha límite de pago o, si no hay, desde el fin del contrato;
  - contratos que terminan en 15 días.
- **Seguridad vial:**
  - **% de cumplimiento del preoperacional**: días-vehículo con extracto vigente (hasta hoy) que tuvieron un preoperacional APTO ese día;
  - preoperacionales no aptos;
  - planes de acción pendientes, vencidos y de prioridad alta;
  - servicios con avisos de jornada;
  - % de planes de viaje leídos y novedades en ruta.
- **Mantenimiento:**
  - cumplimiento del programa anual a la fecha (ejecutadas sobre exigibles);
  - actividades vencidas;
  - mantenimientos preventivos frente a correctivos del periodo;
  - **costo** y **costo por km**: el valor de los mantenimientos sobre los km recorridos, que salen de las lecturas de preoperacionales, inspecciones y mantenimientos;
  - tabla de costo por vehículo.
- **Tendencias de 12 meses** (gráficas):
  - recaudo frente a costo de mantenimiento;
  - extractos expedidos frente al % de cumplimiento del preoperacional.
- **Oficina:** filtra contratos, pagos (por su contrato) y extractos. La flota y el mantenimiento son de toda la empresa.

## Informes en Excel (`ExportarInforme`)
- Pagos recibidos (recibos de caja, incluidos los anulados).
- Estado de contratos (valor, pagado, saldo, fecha límite y días vencido).
- Extractos expedidos.
- Operación de la flota (una fila por servicio y día).
- Preoperacionales e inspecciones.
- Mantenimientos.
- Y el **resumen de gerencia en PDF** (`InformeGerencia::pdf`), con las mismas secciones del tablero.

## Resumen mensual automático
- **Cuándo:** el día 1 a las 7:10 a. m., con el comando `ruta:informe-gerencia` (en el worker, `schedule:work`).
- **A quién:** a los usuarios activos con `ver_gerencia` que tienen correo. Se envía el PDF del mes anterior y cada envío queda en el historial de mensajes (tipo `informe_gerencia`).
- **Webhook:** `gerencia.informe_mensual`, con las cifras principales, si la empresa lo eligió en Integraciones.

## Código
- **Página del tablero:** `app/Filament/App/Pages/TableroGerencia.php` (Dashboard con filtros).
- **Widgets:** `app/Filament/App/Gerencia/Widgets/*`. Están fuera de la carpeta que se descubre sola, para que no salgan en el Inicio. Usan el trait `UsaIndicadores`.
- **Informes:** `app/Filament/App/Pages/Informes.php`.
- **Soporte:** `app/Support/Gerencia/{Indicadores,ExportarInforme,InformeGerencia}.php`.
- **PDF y correo:** `resources/views/pdf/informe-gerencia.blade.php` y `Mail/CorreoInformeGerencia.php`.
- **Comando:** `Console/Commands/InformeMensualGerencia.php`.
- **Pruebas:** `tests/Feature/Gerencia/GerenciaTest.php`, con 6 pruebas.
- **Fechas:** los rangos de fecha usan `whereDate` en los dos extremos, porque en SQLite las fechas se guardan con la hora.
