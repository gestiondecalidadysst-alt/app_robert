# Módulo de carga (RNDC)

**Estado:** fase 0 (módulos por empresa) y fase 1 (datos maestros) construidas el 2026-11-04.

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

## Siguientes fases
2. Remesa y manifiesto (información de carga y de viaje, procesos 1 a 4), con PDF del manifiesto y control frente al SICE-TAC.
3. Cumplidos (procesos 5 y 6) y tiempos logísticos.
4. Anulaciones (7, 8, 9 y 32), contingencia y tablero.

## Código
- Modelos: `TerceroCarga`, `Remolque` y `RegistroRndc`. `Propietario`, `Conductor` y `Vehiculo` implementan `RegistrableRndc`, con el trait `SeRegistraEnRndc`.
- Servicios: `Services\Rndc\Rndc`, `ClienteRndc`, `RespuestaRndc`, `ValidarTercero` y `ValidarVehiculo`.
- Pantallas: `Filament\App\Carga\AccionesRndc` y los recursos `TercerosCarga`, `Remolques` y `RegistrosRndc`.
- PDF: `Support\Pdf\PdfFichaRndc`, vista `pdf/ficha-rndc`.
- Permisos (módulo `carga`): `terceros_carga`, `remolques` y `rndc` (ver y crear = registrar). Administrador y Auxiliar los tienen.
- Pruebas: `tests/Feature/Carga/CargaRndcTest.php` (5).
