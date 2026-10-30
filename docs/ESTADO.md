# Estado del proyecto – Rediseño COOTRASEC (multiempresa)

Última actualización: 2026-10-30

## Hecho
- [x] Relevamiento del legado (`legado/`), decisiones (`DECISIONES.md`) y arquitectura (`nuevo/ARQUITECTURA.md`).
- [x] Propuesta visual aprobada: estilo flonext; pantalla principal = tablero de gerencia.
- [x] **Módulo 1 – Configuración:** construido y con 22 pruebas en verde. Detalle en `nuevo/modulos/configuracion.md`.
- [x] Desplegado en Easypanel: https://prueba.geotracksolucionesapp.online (`/admin` plataforma, `/app` empresas).
- [x] **Módulo 2 – Flota:** propietarios, vehículos (hoja de vida), documentos con historial, estados con novedades y vencimientos. 32 pruebas en verde. Detalle en `nuevo/modulos/flota.md`; esquema y mapeo en `nuevo/datos.md`.
- [x] Corregido: los despliegues ya no borran los ajustes de roles de cada empresa.
- [x] Dockerfile de producción (`Dockerfile`, `docker/`), con los pasos de arranque probados en modo producción.
- [x] Código en GitHub: `gestiondecalidadysst-alt/app_robert` (rama `main`). Es la fuente oficial desde el 2026-10-30; `solucionesappcontact-jpg/app_operation` queda como repositorio anterior y `Escritorio\APP` como copia local.

## Pendiente / bloqueos
- [ ] Redesplegar en Easypanel para publicar el módulo 2 (las migraciones corren solas al arrancar).
- [ ] Configurar el correo (SMTP) para la recuperación de contraseñas.
- [ ] Código fuente del legado: reglas exactas del FUEC (se necesitan en el módulo 4).

## Siguiente paso
1. Redesplegar y revisar Flota en el servidor con datos reales (o `SEED_DEMO=true` para ver la flota demo).
2. Módulo 3 – Conductores: datos, licencia y documentos con historial, estados y novedades, siguiendo el patrón de Flota (`TipoDocumentoVehiculo`, `CambiarEstadoVehiculo`). Antes, agregar sus permisos en `app/Support/Permisos.php`.
