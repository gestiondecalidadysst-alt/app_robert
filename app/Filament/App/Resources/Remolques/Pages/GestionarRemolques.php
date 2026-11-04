<?php

namespace App\Filament\App\Resources\Remolques\Pages;

use App\Filament\App\Resources\Remolques\RemolqueResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class GestionarRemolques extends ManageRecords
{
    protected static string $resource = RemolqueResource::class;

    protected ?string $subheading = 'Se registran en el RNDC como vehículos. El propietario y el tenedor deben estar registrados antes.';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nuevo remolque')];
    }
}
