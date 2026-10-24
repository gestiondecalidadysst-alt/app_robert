# Parámetros (catálogos y configuración)

Patrón común en casi todas: tabla DataTables + botón "Nuevo" que abre modal con Registrar/Actualizar. Datos por `<carpeta>/serverDatos.php`. Campos ocultos estándar en todos los modales: `mi_nivel`, `id-prod` (id del registro), `pro` (acción: nuevo/editar).

| Pantalla | page= | Campos | Endpoint |
|---|---|---|---|
| Menú | `menus_index` | Menú ppal, submenú, icono, checkbox por nivel (5 roles); submenús: menú padre, nombre, icono, archivo a cargar | `menus/server_process.php` |
| Usuarios | `usuarios_index` | Foto, documento, nombres, apellidos, género, oficina, usuario, contraseña, nivel de acceso. Tabla muestra "On Line" | `usuarios/registros_crud.php` (POST multipart), `usuarios/server_process.php` |
| Marcas | `vehiculos_marca_index` | Marca | `vehiculos_marcas/serverDatos.php` |
| Líneas | `vehiculos_lineas_index` | Marca (select), línea | `vehiculos_lineas/serverDatos.php` |
| Mi Empresa | `empresa_index` | NIT, razón social, año habilitación, resolución habilitación, código territorial, municipio, dirección, teléfono, e-mail, representante legal, cédula, expedida en | `empresa/serverDatos.php` |
| Clases | `vehiculos_clases_index` | Clase, **valor administración** (cuota mensual por clase) | `vehiculos_clases/serverDatos.php` |
| Carrocería | `vehiculos_carroceria_index` | Tipo | `vehiculos_carroceria/serverDatos.php` |
| Combustible | `vehiculos_combustible_index` | Tipo | `vehiculos_combustible/serverDatos.php` |
| EPS | `eps_index` | Nombre, teléfono | `eps/serverDatos.php` |
| ARL | `arl_index` | Nombre, teléfono | `arl/serverDatos.php` |
| Nivel académico | `nivel_academico_index` | Descripción | `nivel_academico/serverDatos.php` |
| Ítems alistamiento | `alistamiento_parametro_index` | Descripción (ítems del checklist diario) | `alistamiento_parametro/serverDatos.php` |
| Ítems inspección | `inspeccion_parametros_index` | Descripción | `inspeccion_parametro/serverDatos.php` |
| Empresas afiliadas | `empresa_afiliadas_index` | Descripción (formulario mal etiquetado "Tipo de Combustible") | `empresas_afiliadas/serverDatos.php` |
| Trayectos | `trayectos_index` | Trayecto (texto) | `trayectos_php/serverDatos.php` |
| Tarifas recorridos | `tarifas_recorridos_index` | Origen, destino (selects vacíos -> se cargan por AJAX), valor recorrido | `tarifas_php/serverDatos.php` |
| Conexión N8 | `conexion_n8/index` | Botón "Conectar" -> `pag_operador.php?page=contrato_eventual/conect_n8` (integración externa, probablemente n8n/automatización) | — |

## Valores de catálogo observados (no personales)
- Géneros: MASCULINO, FEMENINO, LGTBI.
- Niveles: ADMINISTRADOR, AUX. ADMINISTRAT, GERENCIA, OPERADOR, RGI – MANTENIMIENTO.
- Servicio vehículo: CORRIENTE, ESPECIAL, PARTICULAR, PUBLICO, URBANO.
- Clases: AUTOMOVIL, BUS, BUSETA, CAMIÓN, CAMIONETA, CAMPERO, MICROBUS, … (9).
- Carrocería: CABINADO, CERRADA, DOBLE CABINA, PICO (PICK UP), SEDAN, VAN, WAGON.
- Combustible: ACPM, DIESEL, GAS NATURAL, GASOLINA (+ un valor erróneo "TRAVEL S.A.S").
- Marcas: 40 registradas.
- Empresas afiliadas: 7 (cooperativas/empresas de transporte con convenio).
- Municipios: 1.125 (catálogo DANE con departamento), usado para ciudad y organismo de tránsito.

## Para el rediseño
- Un único módulo "Catálogos" genérico (nombre + campos extra) en vez de 15 pantallas casi iguales.
- Gestión de roles/permisos por permiso, no por menú; el menú se genera desde los permisos.
- Validar datos de catálogo (ej. "TRAVEL S.A.S" como combustible).
- Aclarar qué hace "Conexión N8" antes de migrarla.
