# Consultas de tránsito (comparendos y RUNT)

**Estado:** construido y probado el 2026-11-03.
**Pedido del usuario:** consultar comparendos y RUNT de conductores y vehículos con PlacApi, sin gastos innecesarios. La plataforma revende las consultas a cada empresa a un precio propio.

## Quién decide qué
| Quién | Dónde | Qué decide |
|---|---|---|
| Superadministrador | Plataforma → **Consultas de tránsito** (`/admin/consultas-transito`) | API key de PlacApi (cifrada, nunca se muestra), costo real por crédito, créditos por tipo y **precio de venta** de cada tipo. |
| Superadministrador | Empresas → editar → **Consultas de tránsito** | Habilita o deshabilita el módulo para la empresa. Puede fijar un **precio especial** por tipo; si lo deja vacío, se usa el general. |
| Superadministrador | Plataforma → **Consumo de consultas** (`/admin/consumo-consultas`) | Ve el consumo por mes y empresa: consultas por tipo, cobradas, valor a cobrar, costo y margen. Ve el detalle y descarga un Excel para facturar. |
| Empresa (Administrador) | Configuración → Integraciones → **Consultas de tránsito** (solo si está habilitado) | Ve los precios y lo gastado en el mes. Elige qué tipos usa, el **tope mensual**, si hay consultas automáticas (tipos y cada cuántos días) y los bloqueos. |
| Usuarios con permiso | Conductor → **Comparendos y licencia**; Vehículo → **Multas y RUNT** | Consultan a mano. Antes de consultar ven el precio y lo gastado. Deciden si el registro entra en las automáticas. |

## Tipos de consulta
| Tipo | Endpoint de PlacApi | Datos que envía | Créditos por defecto |
|---|---|---|---|
| Comparendos del conductor (SIMIT) | `/api/estado-cuenta-transito` | Tipo y número de documento | 2 |
| Licencia del conductor (RUNT) | `/api/licencia` | Documento y primer apellido | 1 |
| Multas del vehículo (SIMIT) | `/api/multas` | Placa y documento del propietario | 1 |
| RUNT del vehículo | `/api/consulta` | Placa y documento del propietario | 1 |

## Reglas
1. **Ninguna consulta se hace sola.** Las automáticas solo corren si la empresa las activa, y solo para los tipos que elija y los conductores o vehículos que marque. Corren cada día a las 5:20 a. m. (`ruta:consultas-transito`), para los que tienen su última consulta exitosa con más días que la frecuencia.
2. **Precio fijo por tipo.** Se cobra solo lo que el proveedor cobró. No se cobran las consultas sin información (404), las que vienen de la caché del proveedor (24 h) ni las que fallan.
3. **Tope mensual.** Si la siguiente consulta supera el tope, se bloquean todas las consultas hasta el mes siguiente o hasta que la empresa suba el tope. En las automáticas se avisa una vez al mes.
4. **Bloqueo del FUEC** (en `Conductor::bloqueosOperacion`):
   - licencia SUSPENDIDA en el RUNT: bloquea si está activo «Bloquear extractos si la licencia está suspendida», que viene **encendido**;
   - comparendos pendientes: bloquean solo si la empresa lo activa, y desde la deuda mínima que defina.
5. **Comparendos con historia.** Cada comparendo guarda cuándo apareció por primera vez y cuándo dejó de aparecer, porque se pagó o se depuró. Los nuevos avisan en la campana y por el webhook `transito.comparendo_nuevo`. Una licencia que pasa a suspendida avisa por `transito.licencia_suspendida`.
6. **RUNT del vehículo.** Se comparan las fechas de vencimiento del SOAT y de la revisión técnico-mecánica con los documentos registrados. Si no coinciden, se avisa.
7. **Permiso «Consultas de comparendos y RUNT (con costo)».**
   - Administrador y Auxiliar: ver y consultar.
   - Gerencia: solo ver.
8. Los campos anteriores del conductor (`comparendos_estado`, `comparendos_cantidad`, `comparendos_consultado_el`) se actualizan con cada consulta de comparendos.

## Resultado y constancia
- En **Historial de consultas** cada consulta que respondió tiene dos acciones: **Ver resultado** y **Constancia** (PDF).
- La notificación que sale al consultar trae el botón **Descargar constancia**. En la pestaña de comparendos también está **Constancia de la última consulta**.
- **La constancia** (`Support\Pdf\PdfConsultaTransito`, vista `pdf/constancia-transito`) se arma con la respuesta guardada, de modo que refleja el momento de la consulta. Lleva:
  - quién fue consultado;
  - el resultado: «No registra comparendos ni multas pendientes de pago», o la lista completa con número, fecha, tipo, infracción, organismo, placa, estado y valor, más la deuda total. Para la licencia lleva las categorías; para el RUNT, el SOAT y la revisión técnico-mecánica;
  - un QR de verificación (`/verificar/consulta-transito/{codigo}`, que no muestra el documento completo);
  - una nota que aclara que **no reemplaza el certificado oficial del SIMIT o del RUNT**.
- **Archivo:** se llama `paz-y-salvo-…pdf` cuando no hay nada pendiente, y `comparendos-…pdf` cuando lo hay.
- Las consultas con error no tienen constancia.

## Código
- `App\Enums\TipoConsultaTransito`.
- Modelos: `ProveedorTransito` (una fila), `ConsultaTransito` y `ComparendoTransito`.
- `App\Services\Transito\PlacApi`: el cliente HTTP.
- `App\Services\Transito\Interpretar`: convierte las respuestas del proveedor, con tolerancia a nombres de campo distintos.
- `App\Actions\Transito\ConsultarTransito`: valida, consulta, cobra, guarda y avisa. `cotizar()` da el precio que muestra la confirmación.
- `App\Jobs\ConsultarTransitoJob`, en la cola baja.
- `App\Console\Commands\ConsultasTransitoAutomaticas`.
- Pantallas:
  - `Admin\Pages\ProveedorConsultas`;
  - `Admin\Pages\ConsumoConsultas`;
  - la sección en `EmpresaResource` y en `App\Pages\Integraciones`;
  - `App\Transito\ComparendosRelationManager` y `ConsultasRelationManager` (historial).
- Pruebas: `tests/Feature/Transito/ConsultasTransitoTest.php` (9).

## Pendiente
- El formato exacto de las respuestas de PlacApi se tomó de su documentación pública. `Interpretar` acepta variantes, pero hay que revisar la primera consulta real en el historial (campo `respuesta`).
