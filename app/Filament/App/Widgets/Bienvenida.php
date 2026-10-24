<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Resources\Oficinas\OficinaResource;
use App\Filament\App\Resources\Roles\RolResource;
use App\Filament\App\Resources\Usuarios\UsuarioResource;
use App\Filament\App\Resources\ValoresAdministracion\ValorAdministracionResource;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * Inicio provisional: el tablero de gerencia se construye en el módulo 8.
 */
class Bienvenida extends Widget
{
    protected string $view = 'filament.app.widgets.bienvenida';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -10;

    protected function getViewData(): array
    {
        $accesos = collect([
            ['Mi empresa', 'Datos, logo y habilitación', Filament::getTenantProfileUrl(), 'ver_empresa'],
            ['Usuarios', 'Personas con acceso y su rol', UsuarioResource::getUrl(), 'ver_usuarios'],
            ['Roles y permisos', 'Qué puede hacer cada rol', RolResource::getUrl(), 'ver_roles'],
            ['Oficinas', 'Sedes de la empresa', OficinaResource::getUrl(), 'ver_oficinas'],
            ['Valor de administración', 'Cuota mensual por clase de vehículo', ValorAdministracionResource::getUrl(), 'ver_valores_administracion'],
        ])->filter(fn (array $a) => auth()->user()->can($a[3]))->values();

        return [
            'usuario' => auth()->user(),
            'empresa' => Filament::getTenant(),
            'accesos' => $accesos,
        ];
    }
}
