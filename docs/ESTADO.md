# Estado del proyecto – Rediseño COOTRASEC (multiempresa)

Última actualización: 2026-10-24

## Hecho
- [x] Relevamiento del legado (`legado/`), decisiones (`DECISIONES.md`) y arquitectura (`nuevo/ARQUITECTURA.md`).
- [x] Propuesta visual aprobada: estilo flonext; pantalla principal = tablero de gerencia.
- [x] **Módulo 1 – Configuración:** construido y con 22 pruebas en verde. Detalle en `nuevo/modulos/configuracion.md`.
- [x] Dockerfile de producción (`Dockerfile`, `docker/`), con los pasos de arranque probados en modo producción.
- [x] Código en GitHub: `solucionesappcontact-jpg/app_operation` (rama `main`). Es la fuente oficial; `Escritorio\APP` es una copia local con el mismo historial.

## Pendiente / bloqueos
- [ ] **Desplegar en Easypanel** (pasos y variables en `README.md`). La imagen Docker no se pudo construir en el entorno de Claude porque Docker Hub está bloqueado; el primer despliegue es la prueba real.
- [ ] Configurar el correo (SMTP) para la recuperación de contraseñas.
- [ ] Código fuente del legado: reglas exactas del FUEC (se necesitan en el módulo 4).

## Siguiente paso
1. Desplegar en Easypanel. Hace falta la dirección del panel y saber si se usará un dominio propio.
2. Módulo 2 – Flota: propietarios, vehículos, documentos con historial y estados. Antes de empezar, agregar sus permisos en `app/Support/Permisos.php`.
