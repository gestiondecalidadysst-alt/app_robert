# Módulos por empresa (fase 0 del módulo de carga)

**Estado:** construido y probado el 2026-11-03.
**Pedido del usuario:** activar módulos por empresa. Para algunas empresas, el de carga puede ser el único que vean.

## Qué se contrata
En `/admin` → Empresas → crear o editar → **Módulos contratados** (`empresas.modulos`, json):

| Módulo | Incluye |
|---|---|
| Transporte especial de pasajeros | Clientes, contratos con firma y recibos, FUEC, planeador, servicios, rutogramas, plantilla de contrato y empresas en convenio. |
| Seguridad vial (PESV) | Preoperacionales e inspecciones, ítems de chequeo, y el preoperacional y la firma en el portal del conductor. |
| Mantenimiento | Planes de acción, mantenimientos, plan preventivo, programa anual, calendario y plantillas. También el mantenimiento en el portal. |
| Gerencia | Tablero, informes y resumen mensual. |
| Manifiestos de carga | En construcción: aparece, pero no se puede marcar. |

- **Base, siempre incluida:**
  - Inicio y Mi empresa;
  - oficinas, usuarios, roles, integraciones, auditoría y valor de administración;
  - vehículos, propietarios, conductores y vencimientos.
- **Consultas de tránsito:** se habilitan aparte, con su propio interruptor.
- **Empresas existentes:** quedaron con los cuatro módulos construidos, así que nada cambia para ellas. Las nuevas arrancan igual, salvo que el superadministrador desmarque.

## Cómo se aplica
- **Permisos:** cada recurso de permiso pertenece a un módulo (`ModuloEmpresa::RECURSOS`). Si la empresa activa no tiene ese módulo, el permiso se niega a todos, también al superadministrador dentro de esa empresa. Esto se aplica en `User::checkPermissionTo` y en `Gate::before`, que para las políticas usa su constante `RECURSO`. El efecto es:
  - desaparece del menú y de Inicio;
  - si alguien escribe la dirección, recibe 403;
  - sus avisos no llegan.
- **Datos:** desmarcar un módulo **no borra datos**. Los roles conservan sus permisos, porque la pantalla de roles oculta esos grupos pero no los quita. Al volver a contratar, todo vuelve como estaba.
- **Integraciones:** las secciones de planeador, recibos, firma y mantenimiento se ocultan sin perder sus valores.
- **Tareas programadas:**
  - seguimiento de firmas: solo empresas con pasajeros;
  - mantenimiento: solo con mantenimiento;
  - resumen mensual: solo con gerencia.
- **Preoperacional sin mantenimiento:** no abre planes de acción y un plan pendiente no deja el vehículo NO APTO, porque no habría cómo cerrarlo. La falla crítica sigue dejándolo NO APTO.
- **Portal del conductor:**
  - sin seguridad vial: no hay preoperacional ni se pide firma, y el extracto se ve el día del servicio;
  - sin mantenimiento: no aparece esa sección;
  - sin pasajeros: no aparecen los FUEC.

## Código
- `App\Enums\ModuloEmpresa`.
- `App\Support\Modulos`: caché por petición y `permisoBloqueado()`.
- `Empresa::tieneModulo()`.
- Migración `2026_11_04_200001_add_modulos_empresas`.
- Pruebas: `tests/Feature/Configuracion/ModulosEmpresaTest.php` (7). Recorren todas las pantallas con y sin módulos.

**Para un módulo nuevo** (por ejemplo, carga):
1. Agregar sus recursos de permiso a `Permisos::RECURSOS`.
2. Mapearlos en `ModuloEmpresa::RECURSOS`.
3. Quitar el caso de `disponible()`.
