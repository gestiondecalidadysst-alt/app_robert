<?php

namespace App\Filament\App\Resources\TercerosCarga\Pages;

use App\Filament\App\Resources\TercerosCarga\TerceroCargaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class GestionarTercerosCarga extends ManageRecords
{
    protected static string $resource = TerceroCargaResource::class;

    protected ?string $subheading = 'Remitentes, destinatarios, titulares de manifiesto y generadores. Propietarios, tenedores y conductores se registran desde Flota y conductores.';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nuevo tercero')];
    }
}
