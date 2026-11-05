<?php

namespace App\Support;

use App\Enums\ModuloEmpresa;
use App\Models\Empresa;
use Spatie\Permission\PermissionRegistrar;

/**
 * Qué módulos tiene cada empresa, con caché por petición (se limpia al guardar la empresa).
 * La empresa activa es la del equipo de permisos (panel de la empresa, avisos y tareas que la fijan).
 */
final class Modulos
{
    /** @var array<int, list<string>> */
    private static array $cache = [];

    public static function activo(int|Empresa|null $empresa, ModuloEmpresa $modulo): bool
    {
        $id = $empresa instanceof Empresa ? $empresa->id : $empresa;
        if (! $id) {
            return true;
        }

        return in_array($modulo->value, self::$cache[$id] ??= self::cargar($id), true);
    }

    /** ¿El permiso "<accion>_<recurso>" es de un módulo que la empresa activa no tiene? */
    public static function permisoBloqueado(string $permiso): bool
    {
        if (! str_contains($permiso, '_')) {
            return false;
        }
        [, $recurso] = explode('_', $permiso, 2);
        $modulo = ModuloEmpresa::deRecurso($recurso);
        if (! $modulo) {
            return false;
        }
        $empresaId = app(PermissionRegistrar::class)->getPermissionsTeamId();

        return $empresaId !== null && ! self::activo((int) $empresaId, $modulo);
    }

    public static function olvidar(?int $empresaId = null): void
    {
        $empresaId === null ? self::$cache = [] : (function () use ($empresaId) {
            unset(self::$cache[$empresaId]);
        })();
    }

    private static function cargar(int $id): array
    {
        $modulos = Empresa::query()->whereKey($id)->value('modulos');
        $modulos = is_string($modulos) ? json_decode($modulos, true) : $modulos;

        return is_array($modulos) ? array_values($modulos) : ModuloEmpresa::porDefecto();
    }
}
