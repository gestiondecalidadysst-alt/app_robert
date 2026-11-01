<?php

namespace App\Filament\App\Resources\Mantenimientos\Pages;

use App\Filament\App\Resources\Mantenimientos\MantenimientoResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class VerMantenimiento extends ViewRecord
{
    protected static string $resource = MantenimientoResource::class;

    public function getTitle(): string|Htmlable
    {
        $r = $this->getRecord();

        return 'Mantenimiento ' . mb_strtolower($r->tipo->getLabel()) . ' · ' . $r->vehiculo?->placa . ' · ' . $r->fecha->format('d/m/Y');
    }

    protected function getHeaderActions(): array
    {
        return [MantenimientoResource::accionAnular()];
    }
}
