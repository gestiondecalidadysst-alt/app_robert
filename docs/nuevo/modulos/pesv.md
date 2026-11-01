# Módulo 5 – PESV (preoperacional e inspecciones) y portal del conductor

Estado: **construido y probado** (15 pruebas propias; 77 en total en verde en SQLite y MySQL). Fecha: 2026-11-01.

## Qué resuelve
Reemplaza en la app anterior "Alistamiento diario", "No realizadas", "Alistamiento día (admin)", "Inspección mensual PESV", "Informe alistamiento diario" e "Informe preoperacional PDF" (ver `legado/modulos/inspecciones.md`).

## Decisiones del usuario (2026-10-31)
1. **Firma única del conductor.** Se registra una sola vez y el sistema la pone en cada preoperacional al generar el PDF. Se puede tomar de tres formas:
   - Dibujada en la pestaña **Firma** del formulario del conductor.
   - Subida como imagen escaneada.
   - Registrada por el propio conductor la primera vez que entra al portal.
2. **El preoperacional lo hace el conductor con su usuario**, desde el portal en el celular.
3. **Tarjeta de servicio:** al expedir un FUEC, cada conductor que va en él ve el servicio en el portal con dos botones, **Hacer preoperacional** y **Ver extracto**.
4. **El extracto se bloquea hasta hacer el preoperacional.** Solo se puede ver el día del servicio y después de un preoperacional **APTO** del vehículo ese día. Con esto se reemplaza la validación que hacía la app anterior antes de expedir el FUEC.

## Flujo
- **Empresa:**
  1. Crea el conductor y registra su firma (o la deja para el portal).
  2. En la hoja de vida usa **Acceso al portal**: se genera una clave temporal, que se muestra una sola vez.
  3. Expide el FUEC.
- **Conductor:**
  1. Entra a `/conductor` con su documento y la clave temporal, y la cambia.
  2. Registra su firma si falta.
  3. Ve la tarjeta del servicio, hace el preoperacional (APTO o NO APTO) y luego ve el extracto.
- **Si el resultado es NO APTO:**
  - El extracto sigue bloqueado.
  - Avisa en la campana a quienes tienen `ver_inspecciones`.
  - El vehículo aparece en **Sin preoperacional hoy**.
  - El conductor puede repetir el preoperacional cuando se corrija la falla.

## Pantallas
### Portal del conductor (`/conductor`)
Hecho en Blade, pensado para celular, sin Filament. Es instalable como app (`public/conductor.webmanifest`).

| Ruta | Qué hace |
|---|---|
| `/conductor/ingresar` | Ingreso con documento y clave. Límite de 10 intentos por minuto por IP. |
| `/conductor/clave` | Cambio de clave, obligatorio con la clave temporal. Mínimo 8 caracteres, con letras y números. Cierra las otras sesiones. |
| `/conductor/firma` | Registro de la firma, solo si falta. Una vez registrada, solo la empresa la puede reemplazar. |
| `/conductor` | Tarjetas de los FUEC vigentes y programados del conductor, con el estado del preoperacional de hoy. Debajo, **Mi vehículo** (vehículos asignados sin servicio hoy) con su propio botón de preoperacional. |
| `/conductor/preoperacional/{vehiculo}?fuec=` | Lista de chequeo:<br>• Cumple, No cumple o N/A por ítem.<br>• Observación obligatoria si no cumple.<br>• Kilometraje.<br>• Barra de avance.<br>• Ubicación, si el conductor la permite.<br>• Declaración. |
| `/conductor/fuec/{fuec}/extracto` | PDF del FUEC, con la regla del bloqueo. |
| `/conductor/historial`, `/conductor/inspeccion/{id}/pdf` | Sus preoperacionales y su PDF. |
| `/verificar/inspeccion/{codigo}` | Pública, se abre con el QR del PDF. Muestra fecha, placa, resultado y el documento oculto salvo los 4 últimos dígitos. |

### Panel de la empresa (grupo **Seguridad vial**)
| Ruta | Qué hace | Permiso |
|---|---|---|
| `inspecciones` | Listado con pestañas (todas, preoperacionales, mensuales) y filtros (resultado, vehículo, conductor, fechas). Subtítulo: lo de hoy. Acciones:<br>• **Sin preoperacional hoy**.<br>• **Informe PDF**: vehículo y rango de hasta 31 días.<br>• **Registrar inspección mensual**. | `ver_inspecciones`, `crear_inspecciones` |
| `inspecciones/{id}` | Detalle con los ítems, la evidencia (firma estampada, IP, ubicación con enlace al mapa, dispositivo) y el PDF. No se edita ni se borra. | `ver_inspecciones` |
| `inspecciones/pendientes-hoy` | Vehículos «Operando» sin preoperacional apto hoy: primero los que tienen FUEC vigente, con su conductor asignado y su último preoperacional. | `ver_inspecciones` |
| `inspecciones/mensual` | Inspección mensual: vehículo, conductor opcional, fecha, kilometraje y los ítems con botones. | `crear_inspecciones` |
| `items-chequeo` | Ítems por tipo (pestañas), agrupados, ordenables arrastrando, con interruptores de crítico y activo. | `*_items_chequeo` |
| Conductor → **Editar → Firma** | Dibujar la firma o subirla en PNG o JPG. | `editar_conductores` |
| Conductor → hoja de vida | Pestaña **Firma y portal**: la firma y el estado del acceso. Acción **Acceso al portal / Restablecer clave**. | `crear_portal_conductor` |
| Configuración → **Usuarios → Nuevo usuario** con el rol **Conductor** | El rol va primero. Al elegir Conductor:<br>• Aparece **Conductor** para elegir uno de los ya registrados.<br>• Se copian su nombre, documento, teléfono, correo y oficina.<br>• El correo es opcional.<br>• La clave es temporal: la que se escriba o una generada, que se muestra una sola vez.<br>• Crea el mismo acceso que la hoja de vida. | `crear_usuarios` |

## Archivos clave
### Modelos y reglas
- **Modelos:**
  - `app/Models/ItemChequeo.php`.
  - `app/Models/Inspeccion.php`: `fallas()`, `apta()`, `urlVerificacion()`, scopes `preoperacionales` y `delDia`.
  - `Vehiculo::preoperacionalDelDia()`.
  - `Conductor::tieneFirma()`.
  - `User::conductores()`.
- **Enums:** `app/Enums/TipoInspeccion.php` y `ResultadoInspeccion.php`.
- **Ítems por defecto:** `app/Support/Pesv/ItemsBase.php`. Son 22 del preoperacional y 25 del mensual, sacados de la app anterior, agrupados y sin repetidos, con los críticos marcados. Cada empresa los recibe de dos formas:
  - Al crearse, con `CrearEmpresa`.
  - Al desplegar, con `ItemsChequeoSeeder`, en `docker/entrypoint.sh`.
- **`app/Actions/Pesv/RegistrarInspeccion.php`**: única forma de registrar una inspección.
  - Valida:
    - Que el vehículo y el conductor sean de la empresa.
    - Que estén todos los ítems activos respondidos.
    - Que cada ítem que no cumple tenga observación.
    - En el preoperacional: que haya conductor con firma y kilometraje, y que no exista ya un preoperacional apto hoy de ese vehículo.
    - Que el FUEC sea del vehículo y del conductor y esté vigente hoy.
  - Resultado: **NO APTO si falla un ítem crítico**.
  - Guarda una copia de los ítems, del vehículo, del conductor (con su licencia), del FUEC y de la empresa, y una **copia de la firma** del conductor.
  - Registra quién lo hizo, la IP, el dispositivo y la ubicación.
  - Avisa en la campana cuando el resultado es NO APTO.
- `app/Actions/Pesv/GuardarFirmaConductor.php`:
  - Recibe la firma dibujada (validada con `FirmaManuscrita`) o un PNG o JPG de máximo 2 MB.
  - La guarda en el disco privado.
  - Borra la anterior, porque las inspecciones tienen su propia copia.
  - Deja el cambio en la auditoría.
- `app/Actions/Pesv/CrearAccesoConductor.php`:
  - Crea o reutiliza el usuario: una persona que es conductor en varias empresas usa **un solo usuario** y ve sus servicios de todas.
  - El usuario no tiene correo.
  - Genera una clave temporal de 8 caracteres fácil de dictar, con `debe_cambiar_clave`.
  - Rechaza a los conductores retirados.
  - Vincula el usuario a la empresa con el rol **Conductor**, para que aparezca en Configuración → Usuarios:
    - Ese rol **no da acceso al panel `/app`**, porque `User::empresasDisponibles()` excluye las empresas donde el usuario es Conductor.
    - Si se desactiva el acceso en Usuarios, el conductor pierde el portal para esa empresa.
    - Si se lo quita de la empresa, se desvincula del conductor.
  - Lo usan la hoja de vida del conductor y la creación de usuarios con el rol Conductor (`Usuarios/Pages/CrearUsuario`). Acepta una clave inicial opcional.
- `app/Support/Pesv/ServiciosConductor.php`: arma lo que ve el conductor (sus FUEC, la tarjeta con la regla del extracto, los vehículos asignados) y revisa a qué vehículos puede hacerles preoperacional.

### PDF
`app/Support/Pdf/PdfInspeccion.php` arma dos documentos:
- **Preoperacional o inspección mensual** (`pdf/inspeccion.blade.php`):
  - Datos del vehículo y del conductor.
  - Ítems por grupo con las columnas Cumple, No cumple y N/A, y su observación.
  - Resultado, firma estampada y leyenda "Firmado electrónicamente por el conductor… con su usuario personal".
  - IP, ubicación y QR de verificación.
- **Informe** (`pdf/informe-preoperacional.blade.php`), en hoja horizontal:
  - Matriz de ítems por días (hasta 31) con ✓, ✗ y N/A.
  - Filas de resultado, kilometraje y conductor.
  - Lista de novedades y observaciones.

### Portal
- `app/Http/Middleware/EsConductor.php` (alias `conductor`):
  - Exige sesión y al menos una hoja de vida de conductor no retirada.
  - Obliga a cambiar la clave temporal.
  - Envía las cabeceras `no-store` y `no-referrer`.
- Controladores `app/Http/Controllers/Conductor/{Acceso,Portal,Preoperacional}Controller.php` y `VerificarInspeccionController.php`.
- Vistas `resources/views/conductor/*` e `inspeccion/verificar.blade.php`.
- Íconos de la app en `public/images/conductor-{192,512}.png`.

### Panel
- `app/Filament/App/Resources/Inspecciones/*`: listado, detalle, mensual y pendientes.
- `ItemsChequeo/*`.
- Campo de firma para Filament: `resources/views/filament/campos/firma.blade.php` (lienzo con Alpine).
- Firma en el formulario del conductor: `ConductorForm::firma()` y el trait `Conductores/Concerns/GuardaFirmaDelFormulario.php`.

### Permisos
- **Recursos nuevos:**
  - `items_chequeo`: ver, crear, editar y eliminar.
  - `inspecciones`: ver y crear.
  - `portal_conductor`: crear.
- **Permisos por rol:**
  - Gerencia: solo ver.
  - Auxiliar: ver ítems, ver y crear inspecciones, y dar acceso al portal.
  - Mantenimiento: ver ítems, y ver y crear inspecciones.

### Base de datos
`database/migrations/2026_11_01_100001_create_pesv.php` (idempotente) hace estos cambios:
- `users.email` pasa a ser opcional y se agrega `users.debe_cambiar_clave`.
- Se agregan `conductores.firma_path` y `firma_actualizada_at`.
- Se crean las tablas `items_chequeo` e `inspecciones`.

El esquema está en `nuevo/datos.md`. Hay un seeder de demostración, `DemoPesvSeeder`:
- Da acceso al portal al conductor 20000001 con la clave `Ruta2026*` y le registra una firma.
- Crea 6 preoperacionales de días anteriores, uno de ellos NO APTO, y una inspección mensual.

## Archivos de firmas (importante al desplegar)
- Las firmas del conductor y sus copias en cada inspección están en `storage/app/private`. **El servicio web necesita un volumen montado en `/var/www/html/storage/app`** (README, paso 4). Sin volumen, cada despliegue borra esos archivos.
- Si el archivo de la firma del conductor no existe, `Conductor::tieneFirma()` devuelve falso: el portal la vuelve a pedir y `RegistrarInspeccion` no registra preoperacionales sin firma.
- `GuardarFirmaConductor` comprueba que el archivo quedó escrito; si no, avisa y deja el error en el log.
- El informe PDF incluye la sección **Firmas de los conductores**: la firma de cada conductor del período con sus días.

## Pendientes / ideas
- Aviso por SMS al conductor cuando se le expide un FUEC (con `Mensajero`; consume saldo, así que sería opcional).
- Fotos de las novedades en el preoperacional y orden de mantenimiento correctiva automática cuando un ítem crítico falla (módulo de mantenimiento).
- Permitir el preoperacional sin conexión (service worker). Hoy el portal necesita internet.
- Programar un informe diario de vehículos sin preoperacional.
