<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Spatie\Permission\Models\Role;

/**
 * Rol de usuario dentro de una empresa (spatie/laravel-permission con teams = empresas).
 */
class Rol extends Role
{
    use PerteneceAEmpresa;

    public const ADMINISTRADOR = 'Administrador';

    public const GERENCIA = 'Gerencia';

    public const AUXILIAR = 'Auxiliar administrativo';

    public const MANTENIMIENTO = 'Mantenimiento';

    public const CONDUCTOR = 'Conductor';
}
