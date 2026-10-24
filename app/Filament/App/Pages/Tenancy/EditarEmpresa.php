<?php

namespace App\Filament\App\Pages\Tenancy;

use App\Filament\Compartido\FormularioEmpresa;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/**
 * "Mi empresa": la empresa edita sus propios datos.
 */
class EditarEmpresa extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Mi empresa';
    }

    public static function canView(Model $tenant): bool
    {
        return auth()->user()?->can('ver_empresa') ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(FormularioEmpresa::componentes())
            ->disabled(fn (): bool => ! auth()->user()?->can('editar_empresa'));
    }
}
