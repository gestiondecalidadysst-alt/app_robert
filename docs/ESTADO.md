# Estado del proyecto – Rediseño COOTRASEC (multiempresa)

Última actualización: 2026-10-24

## Hecho
- [x] Relevamiento del legado (`legado/`), decisiones (`DECISIONES.md`) y arquitectura (`nuevo/ARQUITECTURA.md`).
- [x] Propuesta visual aprobada: estilo flonext; pantalla principal = tablero de gerencia.
- [x] **Módulo 1 – Configuración:** construido y con 22 pruebas en verde. Detalle en `nuevo/modulos/configuracion.md`.
- [x] Proyecto Laravel 13 + Filament 5 copiado en `Escritorio\APP`.

## Pendiente / bloqueos
- [x] Dockerfile de producción (`Dockerfile`, `docker/`), con los pasos de arranque probados en modo producción. Pasos de Easypanel en `README.md`.
- [ ] Subir el repositorio a GitHub: el repo git local ya tiene el commit inicial, falta la conexión con GitHub.
- [ ] Desplegar en Easypanel.
- [ ] Configurar el correo (SMTP) para la recuperación de contraseñas.
- [ ] Código fuente del legado: reglas exactas del FUEC (se necesitan en el módulo 4).

## Siguiente paso
Módulo 2 – Flota:
- Propietarios y vehículos.
- Documentos con historial.
- Estados del vehículo.

Antes de empezar, agregar sus permisos en `app/Support/Permisos.php`.
