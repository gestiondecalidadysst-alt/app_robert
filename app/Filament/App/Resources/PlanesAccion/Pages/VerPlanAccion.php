<?php

namespace App\Filament\App\Resources\PlanesAccion\Pages;

use App\Filament\App\Resources\PlanesAccion\PlanAccionResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class VerPlanAccion extends ViewRecord
{
    protected static string $resource = PlanAccionResource::class;

    public function getTitle(): string|Htmlable
    {
        $r = $this->getRecord();

        return "{$r->numero_formateado} · {$r->vehiculo?->placa}";
    }

    public function getSubheading(): ?string
    {
        return $this->getRecord()->titulo;
    }

    protected function getHeaderActions(): array
    {
        return PlanAccionResource::accionesGestion();
    }
}
