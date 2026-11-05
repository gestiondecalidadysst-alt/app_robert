# Módulo de carga (RNDC)

**Estado:** fases 0 a 5 construidas (2026-11-04 y 05). Queda el tablero del módulo.

**Referencia:**
- Manual RNDC Web-Service v2.3 (2014), entregado por el usuario.
- Resolución 20263040016075 de 2026, que actualiza el RNDC. Los campos pueden haber cambiado frente al manual de 2014: revisar en la primera conexión real.

**Decisiones del usuario:**
- La empresa aún no tiene usuario del RNDC: el sistema queda listo para conectar y, mientras tanto, se descarga la ficha en PDF.
- Hay clientes de los dos tipos: empresas de transporte y generadores de carga.
- El cobro lo decide la plataforma con cada cliente: valor fijo mensual o por manifiesto.

## Fase 1 – Datos maestros
| Qué | Dónde | Proceso del RNDC |
|---|---|---|
| Remitentes, destinatarios, titulares y generadores | Carga → Terceros (`terceros_carga`). Una empresa con varias sedes se registra una vez por sede. | 11 |
| Propietarios y tenedores | Flota y conductores → Propietarios. Se reutilizan. | 11 |
| Conductores | Flota y conductores → Conductores. Se usa la licencia vigente de sus documentos. | 11 |
| Camiones rígidos y tractocamiones | Vehículo → pestaña **Carga (RNDC)**: configuración, códigos RNDC, peso vacío, combustible, tenedor y NIT de la aseguradora del SOAT. | 12 |
| Semirremolques y remolques | Carga → Remolques (`remolques`), con placa R + 5 números. | 12 |

**Acciones (menú «RNDC»)** en cada registro de terceros, remolques, propietarios, conductores y vehículos:
- **Registrar en el RNDC.** Solo aparece con la conexión activa. Primero valida lo que el diccionario de datos exige; un vehículo, además, exige que su propietario y su tenedor ya estén registrados. Luego envía y guarda el radicado (`ingresoid`) o los errores con su código (TER020, VEH010…).
- **Anotar radicado de la web del RNDC.** Para lo que se registró directamente en la web del Ministerio.
- **Ficha RNDC (PDF).** Lista cada dato con su variable del RNDC y lo que falta corregir. También se descarga en lote seleccionando varios registros.
- **Ver XML.** Muestra el XML tal como se enviaría, sin la clave.

**Estado de cada registro:**
- Sin registrar.
- Registrado (con el número de radicado).
- Con errores.
- Cambió: volver a registrar. Aparece cuando se editó después del último registro.

**Bitácora RNDC:** cada envío o registro manual con lo enviado, la respuesta y el usuario.

## Conexión (web service)
- **Credenciales:** Integraciones → **RNDC**: usuario y clave cifrados, ambiente (pruebas o producción), interruptor de conexión y rol de la empresa (transportadora, generador o ambos). La conexión no se activa sin usuario y clave.
- **Llamada:** SOAP al método `AtenderMensajeRNDC` con el parámetro `Request`, en ISO-8859-1.
  - `<root>` lleva `<acceso>`, `<solicitud>` (con `tipo` y `procesoid`) y `<variables>`.
  - La respuesta es `<root><ingresoid>…</ingresoid></root>` o el texto de los errores.
  - Lo implementan `ClienteRndc` y `RespuestaRndc`.
- **Dirección:** `config/rndc.php` (`RNDC_URL`, `RNDC_URL_PRUEBAS`, `RNDC_NAMESPACE`); se cambia sin tocar el código. Por defecto es `http://rndcws.mintransporte.gov.co:8080/ws/svr008w.dll/soap/IBPMServices`.

**Por confirmar en la primera conexión real:**
- el espacio de nombres SOAP;
- el formato exacto de los errores;
- si `NUMNITEMPRESATRANSPORTE` va con o sin dígito de verificación (hoy va sin él, como en el ejemplo del manual);
- que el servidor de Easypanel pueda salir al puerto 8080 del Ministerio.

## Cobro (superadministrador)
En `/admin`, Empresas → Módulos contratados, al marcar **Carga (RNDC)** se elige el cobro: valor fijo mensual o por manifiesto expedido, con su valor. La medición de manifiestos llega con la fase 2.

## Códigos
- **Configuraciones:** tomadas del manual (50–56 rígidos y tractocamiones; 61–64 semirremolques; 71–74 remolques; 81–85 balanceados), en `App\Support\Carga\CatalogoRndc`. Los números intermedios de cada grupo siguen el patrón del manual; **verificar con la tabla «Configuración Vehículos» del anexo del RNDC**.
- **Tablas del anexo:** marcas, líneas, colores, carrocerías y aseguradoras se digitan con su código.
- **Municipio:** código DANE de 5 dígitos + `000`.
- **Tipos de identificación:** CC→C, NIT→N, PA→P, CE→E, TI→T, NUIP→U. PEP y PPT no los admite el RNDC.

## Fase 2 – Remesa y manifiesto
**Estado:** construida el 2026-11-05.

**Pantallas:**
- **Carga → Remesas:** una remesa por envío, con producto, cantidad, póliza, remitente y destinatario, y tiempos pactados y reales de cargue.
- **Carga → Manifiestos:** encabezado, vehículo y conductor, titular, valores y remesas asociadas. Al guardar enlaza las remesas al manifiesto y suelta las que se quiten.

**Numeración:** consecutivo único por empresa, automático (`RT-000001`, `MC-000001`).

**Validaciones del Manual RNDC v2.3:**
- Remesa: remitente y destinatario deben estar registrados en el RNDC; municipios distintos (salvo desechos peligrosos [5] o contenedor vacío [V]); si hay póliza, debe traer número, vencimiento y NIT de la aseguradora.
- Manifiesto: vehículo (de carga), conductor, titular y todas las remesas deben estar registrados antes; anticipo ≤ flete; SICE-TAC (si se consultó): flete ≥ valor mínimo.

**Valores:** se calcula saldo = flete − anticipo − retención fuente − reteica.

**Anulación:** remesa con motivo libre (solo si no está en un manifiesto); manifiesto con motivo del RNDC (D = error en digitación, S = cancelación del servicio). Ninguna anulación se reporta todavía al RNDC (fase 4).

**PDF del manifiesto:** `Support\Pdf\PdfManifiestoCarga`, vista `pdf/manifiesto-carga`, carta. Lleva encabezado de la empresa, QR, estado del RNDC con radicado, datos del viaje, tabla de remesas, valores y observaciones. Sello ANULADO cuando aplica.

**Verificación pública:** `/verificar/manifiesto/{codigo}` (vista + PDF descargable), con límite de 30 por minuto.

**Envío al RNDC:** procesos 3 (remesa) y 4 (manifiesto). El cliente soporta grupos anidados (`REMESASMAN procesoid="43"`) para asociar las remesas al manifiesto.

**Pruebas:** `tests/Feature/Carga/RemesasManifiestosTest.php` (8).

## Fase 3 – Cumplidos y tiempos reales
**Estado:** construida el 2026-11-05.

**Qué se registra:**
- **Cumplido de remesa** (proceso 5): tiempos reales de descargue (llegada, entrada y salida), cantidad entregada; o suspensión con motivo (A/V/S). La acción **Cumplir / Tiempos** aparece en cada remesa.
- **Cumplido de manifiesto** (proceso 6): fecha de entrega de documentos, tipo (C normal o S suspensión con motivo y consecuencia), y ajustes al pago:
  - adicionales por cargue, descargue o flete, con motivo (C merma, R variación de ruta, O otro);
  - descuento al flete, con motivo (F faltante, D deterioro, C merma, V viaje incompleto);
  - sobreanticipo.
- **Neto a pagar** al titular: se recalcula automáticamente (flete ± adicionales − descuento − anticipo − sobreanticipo − retefuente − reteica).

**Reglas del manual:**
- El cumplido del manifiesto exige que todas sus remesas estén cumplidas en el RNDC.
- En suspensión no se reportan los tiempos (ni cantidad entregada).
- Fecha de entrega de documentos no puede ser futura.
- Sobreanticipo ≤ saldo neto.
- Cantidad entregada ≤ cantidad cargada.

**Pantallas:** Carga → **Cumplidos** (de manifiesto) y la acción **Cumplir / Tiempos** en cada remesa. El envío al RNDC va por el menú RNDC estándar (`crear_rndc`), la ficha PDF y el "Ver XML" también funcionan para los cumplidos.

**Portal del conductor:** por ahora los tiempos los registra la oficina. Un paso siguiente es el botón del celular ("Llegué", "Entré", "Salí") en el portal; queda para después porque el portal actual maneja FUEC de pasajeros.

**Pruebas:** `tests/Feature/Carga/CumplidosTest.php` (5).

## Siguientes fases
 (información de carga y de viaje, procesos 1 a 4), con PDF del manifiesto y control frente al SICE-TAC.
4. Anulaciones (7, 8, 9 y 32) reportadas al RNDC, contingencia y tablero del módulo.
5. Tiempos logísticos desde el celular (portal del conductor para carga).

## Código
- Modelos: `TerceroCarga`, `Remolque` y `RegistroRndc`. `Propietario`, `Conductor` y `Vehiculo` implementan `RegistrableRndc`, con el trait `SeRegistraEnRndc`.
- Servicios: `Services\Rndc\Rndc`, `ClienteRndc`, `RespuestaRndc`, `ValidarTercero` y `ValidarVehiculo`.
- Pantallas: `Filament\App\Carga\AccionesRndc` y los recursos `TercerosCarga`, `Remolques` y `RegistrosRndc`.
- PDF: `Support\Pdf\PdfFichaRndc`, vista `pdf/ficha-rndc`.
- Permisos (módulo `carga`): `terceros_carga`, `remolques` y `rndc` (ver y crear = registrar). Administrador y Auxiliar los tienen.
- Pruebas: `tests/Feature/Carga/CargaRndcTest.php` (5).

## Asociación de remesas al manifiesto
La remesa se elige en el paso «Remesas» del asistente. El campo `remesas_ids` no es
una columna: se aparta en `mutateFormDataBeforeCreate/Save` con el trait
`Pages\Concerns\AsociaRemesas` (compartido por Crear y Editar) y se aplica en
`afterSave()` sobre `remesas_carga.manifiesto_id`.

Reglas:
- Sin remesa el guardado se detiene (`Halt`) con un aviso: un manifiesto sin remesa
  no es válido ante el RNDC.
- Al editar, las remesas que se quitan quedan libres (`manifiesto_id = null`) para
  otro manifiesto.
- El contador «Remesas» del listado se muestra en rojo cuando es 0.

No usar `dehydrated(false)` + `afterStateHydrated()` para este campo: así viajaba
antes por el estado crudo de Livewire y el manifiesto podía expedirse sin remesa
en silencio. Pruebas: `tests/Feature/Carga/ManifiestoRemesasTest.php` (4).


## Fase 4 – Anulaciones y contingencia (2026-11-05)

### Anulaciones ante el RNDC
`AnulableRndc` + `Rndc::anular()`. Pantalla: `Filament\App\Carga\AnularConRndc`.

| Documento | Proceso | Variables |
|---|---|---|
| Remesa | 9 | `CONSECUTIVOREMESA`, `MOTIVOREVERSAREMESA` (A anular / L liberar), `MOTIVOANULACIONREMESA` (D/S) y, al liberar, `CODMUNICIPIOTRANSBORDO` y `MOTIVOTRANSBORDOREMESA` (A accidente, V varada, S siniestro) |
| Manifiesto | 32 | `NUMMANIFIESTOCARGA`, `MOTIVOANULACIONMANIFIESTO` (D/S) |

Reglas del manual (9.11 y 9.12), comprobadas antes de enviar:
- Un manifiesto no se anula si está cumplido o si alguna de sus remesas lo está.
- Una remesa dentro de un manifiesto no se anula: se **libera** (transbordo).
- Una vez anulado, el RNDC no deja reutilizar el consecutivo.

Si el RNDC rechaza la anulación **no se anula nada aquí**: un documento anulado en
el sistema y vigente ante el Ministerio es peor que no anularlo.

### Contingencia
`RespuestaRndc::reintentable()` distingue el servicio caído (sin conexión, 5xx, 408,
429) del rechazo de datos. Solo lo primero se reintenta.

- Estado `contingencia` en la bitácora, con `intentos` y `reintentar_a`.
- Esperas: 5 min, 15, 30, 1 h, 2 h, 4 h. Agotadas, pide radicar en la web del RNDC.
- Reencola `ruta:rndc-contingencia` (cada diez minutos), **no** el propio job: así no
  se gastan los intentos de golpe con cola síncrona y sobrevive al reinicio del worker.

## Fase 5 – Tiempos logísticos (2026-11-05)
Tabla `tiempos_logisticos`: seis momentos por remesa (llegada, entrada y salida en
cargue y en descargue), con hora del servidor y ubicación opcional del celular.

- `Support\Carga\TiemposLogisticos` obliga el orden y el punto: llegada → entrada →
  salida, primero cargue y después descargue.
- Portal del conductor: `/conductor/carga`, un botón por remesa para lo siguiente que
  toca. Si el navegador niega la ubicación, el reporte se envía igual.
- Panel: acción «Tiempos logísticos» para completar desde la oficina, columna n/6 y
  cálculo del excedente sobre lo pactado (ese tiempo de espera es cobrable).
- Los tiempos rellenan `entrada_cargue` y `salida_cargue`, que ya viajan en el cumplido.

## SICE-TAC
El Ministerio no publica servicio de consulta: el valor lo extrae el propio RNDC al
radicar (Manual de Generadores de la Supertransporte). Por eso la empresa carga las
tablas publicadas en **Carga → SICE-TAC** (`tarifas_sicetac`, permiso del mismo
nombre), por ruta, configuración y vigencia; la configuración exacta manda sobre la
genérica. La carga masiva se hace pegando CSV con municipios por código DANE.

`ManifiestoCarga::minimoSicetac()` usa el valor digitado o, si no hay, la tarifa:
dejar el campo vacío ya no sirve para saltarse el tope.

## Plazos y cobertura
- **Rutas municipales:** admitidas (origen = destino). La Resolución 20263040016075 de
  2026 las incorporó; había que comprobar que nada las rechazara.
- **Pago del saldo:** `fechaLimitePagoSaldo()` son cinco días hábiles desde el cumplido
  (solo se saltan sábados y domingos, no festivos: la fecha más exigente). El listado
  marca «Vencido», «Por vencer» o «Pagado», con filtro propio.

## Pruebas
`tests/Feature/Carga/`: `CargaRndcTest`, `RemesasManifiestosTest`, `CumplidosTest`,
`ManifiestoRemesasTest`, `AnulacionesRndcTest`, `ContingenciaRndcTest`,
`TiemposLogisticosTest`, `SicetacTest`, `PlazosYRutasTest`.

## Lo que falta
- Tablero del módulo (indicadores de carga).
- Reporte en tiempo real de los tiempos logísticos al RNDC: el proceso del web service
  no está en el manual de 2014; hoy viajan en el cumplido. Confirmar en la primera
  conexión real.
