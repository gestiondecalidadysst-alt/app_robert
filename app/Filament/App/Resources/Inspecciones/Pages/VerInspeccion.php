<?php

namespace App\Filament\App\Resources\Inspecciones\Pages;

use App\Filament\App\Resources\Inspecciones\InspeccionResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class VerInspeccion extends ViewRecord
{
    protected static string $resource = InspeccionResource::class;

    public function getTitle(): string|Htmlable
    {
        $r = $this->getRecord();

        return $r->tipo->corto() . ' · ' . $r->placa . ' · ' . $r->fecha->format('d/m/Y');
    }

    protected function getHeaderActions(): array
    {
        return [InspeccionResource::accionPdf()];
    }
}
