# App actual (legado): COOTRASEC – SolucionesApp

- URL base: `https://www.solucionesapp.net/cootrasec/index_cpanel.php?page=<pagina>`
- Tipo: panel administrativo PHP (plantilla "SB Admin" / Bootstrap + jQuery + DataTables). Cada pantalla es un `include` vía `?page=`; los datos se cargan por AJAX desde archivos `<modulo>/server*.php`, `registros_crud.php` y `cargar_datos.php`.
- Dominio: **transporte especial de pasajeros** (cooperativa). Gestiona flota, conductores, documentación legal, extractos de contrato (FUEC), contratos, planillas/tiquetes, inspecciones preoperacionales (PESV), mantenimientos, combustible y pagos.
- Relevado el 2026-10-24 navegando como usuario con nivel ADMINISTRADOR (solo lectura, sin guardar nada).

## Tecnología del legado (verificado 2026-10-24 desde el navegador)
- PHP plano, **sin framework**: no hay cookie ni cabeceras de Laravel, Symfony, CodeIgniter, etc. Solo `PHPSESSID` (sesión nativa de PHP).
- Servidor LiteSpeed (hosting compartido tipo cPanel).
- Enrutado: `index_cpanel.php?page=<archivo>` que incluye `<archivo>.php`; vista aparte `pag_operador.php` para el operador.
- Backend: un archivo PHP por acción dentro de cada carpeta (`server_process.php`, `serverDatos.php`, `registros_crud.php`, `cargar_datos.php`, `procesar_busquedad.php`), llamados por AJAX con jQuery.
- Frontend: plantilla NiceAdmin/SB Admin, Bootstrap 4 y 5 mezclados, jQuery 1.11.1 (2014), jQuery UI, DataTables 1.10 (con botones Excel/PDF), Chosen, Alertify, clockpicker, datepicker, ApexCharts, Quill y TinyMCE.
- Librerías copiadas a mano dentro del proyecto (sin Composer ni npm visibles); recursos con `?random=` para evitar la caché.

## Roles (niveles de acceso)
1. ADMINISTRADOR
2. AUX. ADMINISTRATIVO
3. RGI – MANTENIMIENTO
4. OPERADOR (conductor / despachador)
5. GERENCIA

Los menús se asignan por nivel desde `Parámetros > Menú` (checkbox por nivel). Existe una vista separada `pag_operador.php` para el operador.

## Oficinas / despachos
Usuarios y planillas se asocian a una oficina (despacho). Hoy hay 2: Montería y Montería Oppal.

## Mapa de módulos -> documento
| Menú | Pantallas | Doc |
|---|---|---|
| Inicio | Alertas del sistema (vencimientos) | [alertas.md](modulos/alertas.md) |
| PARAMETROS | Menú, Usuarios, Marcas, Líneas, Mi Empresa, Clases, Carrocería, Combustible, EPS, ARL, Nivel académico, Ítems alistamiento, Ítems inspección, Empresas afiliadas, Trayectos, Tarifas, Conexión N8 | [parametros.md](modulos/parametros.md) |
| PROPIETARIOS / VEHICULOS | Propietarios, Vehículos, Control documentos, Novedades, Ficha técnica, Programa mantenimiento | [vehiculos.md](modulos/vehiculos.md) |
| OPERADORES | Registro, Documentos, Novedades | [operadores.md](modulos/operadores.md) |
| EXTRACTOS | Generar, Administración (404), Informe expedidos, Modificar consecutivo, Programador, Programador final | [extractos.md](modulos/extractos.md) |
| PRE OPERACIONAL / INSPECCIÓN | Alistamiento diario (checklist + firma), No realizadas, Admin alistamiento, Inspección mensual PESV, informes | [inspecciones.md](modulos/inspecciones.md) |
| CONTRATOS | Elaborar, Bitácora, Administrar, Abonos/Pagos | [contratos.md](modulos/contratos.md) |
| PLANILLAS Y TIQUETES / ADMINISTRATIVO | Tiquetes y planillas, Cuadre de caja, Planillas admin, Historial tiquetes, Cuadre por oficinas, Liquidar vehículos | [planillas-tiquetes.md](modulos/planillas-tiquetes.md) |
| COMBUSTIBLE / MANTENIMIENTOS | Recibos combustible, Mantenimientos, Informe | [mantenimiento-combustible.md](modulos/mantenimiento-combustible.md) |
| ADMINISTRACION | Pago administración (cuota de afiliación del vehículo) | [pagos-administracion.md](modulos/pagos-administracion.md) |
| INFORME OPERACIONES / FORMATOS | Pagos del día, Estado contratos, Operación flota, informes de inspección, formatos | [informes.md](modulos/informes.md) |

Entidades y relaciones: [datos.md](datos.md). Términos: [../GLOSARIO.md](../GLOSARIO.md).

## Problemas generales observados (candidatos para el rediseño)
- Menú duplicado: "Planillas Administrar" e "Historial Tiquetes" aparecen en dos menús; "Ficha técnica" y "Programa mantenimiento" también en FORMATOS; "Datos Tarjeta Propiedad" apunta a la misma página de vehículos.
- `Extractos > Administración` devuelve 404.
- Formularios repetidos casi idénticos (Generar extracto ≈ Programador final; Elaborar contrato ≈ Administrar contrato) -> lógica duplicada.
- Documentos del vehículo y del conductor se capturan como columnas fijas de fecha, no como historial de documentos.
- Validaciones de vigencia (SOAT, póliza, técnico-mecánica, tarjeta de operación, licencia, etc.) se calculan en el navegador con campos ocultos.
- Listas desplegables gigantes (1.126 municipios, 274 conductores, 2.398 contratos) sin búsqueda en servidor.
- Hora capturada como 3 selects (hora/minutos/AM-PM) o texto libre.
- Errores de texto en la interfaz ("Cerrar Sección", "Obervaciones", "recoridos", "Mantenimento").
- Notificaciones/mensajes del encabezado son datos de ejemplo de la plantilla.
- Ítems del checklist diario están parametrizados, pero los de la inspección mensual parecen fijos (21–30 ítems).
- El tipo "Empresas afiliadas" reutiliza el formulario de "Tipo de Combustible" (etiqueta incorrecta).
