# Alertas del sistema (dashboard)

- URL: `?page=alertas_sistema` (pantalla de inicio tras login). Datos: `alertas/server_soat.php`.
- Rol: todos los que entran al panel.

## Qué muestra
Cinco tablas con columnas **Placa | Vigencia | Vence** (texto "EN N DIAS" o vacío si ya venció):
1. Documento SOAT
2. Póliza (RC-RCE)
3. Tarjetas de operación
4. Técnico (bimensual) – revisión preventiva cada 2 meses
5. Técnico-mecánica (anual)

Cada tabla paginada (10 filas) con botón "Ver detalles" que abre un listado imprimible (`PrintSoat()`, `PrintPolizas()`, `PrintTopera()`, `PrintTbMes()`, `PrintAnuales()`).

## Reglas observadas
- Se listan documentos próximos a vencer (≈15 días) **y** ya vencidos; los vencidos aparecen sin texto en "Vence" (no se distinguen visualmente bien).
- Fechas en formato `dd-mm-aaaa`.
- Licencias de conducción y seguridad social de conductores **no** aparecen en el dashboard.

## Para el rediseño
- Unificar en un solo panel de "vencimientos" con filtros (vehículo/conductor, tipo, vencido/próximo) y semáforo.
- Incluir documentos de conductores (licencia, salud) y pagos de administración.
- Notificaciones (correo/WhatsApp) en vez de depender de entrar al panel.
