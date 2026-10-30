<?php

namespace App\Filament\App\Resources\Conductores\Pages;

use App\Enums\EstadoConductor;
use App\Filament\App\Resources\Conductores\ConductorResource;
use App\Models\Conductor;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListarConductores extends ListRecords
{
    protected static string $resource = ConductorResource::class;

    public function getSubheading(): ?string
    {
        $conteo = Conductor::query()->toBase()->selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado');

        return sprintf(
            '%d activos · %d suspendidos · %d retirados',
            $conteo[EstadoConductor::Activo->value] ?? 0,
            $conteo[EstadoConductor::Suspendido->value] ?? 0,
            $conteo[EstadoConductor::Retirado->value] ?? 0,
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nuevo conductor'),
        ];
    }
}
