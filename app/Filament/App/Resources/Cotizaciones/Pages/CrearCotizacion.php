<?php

namespace App\Filament\App\Resources\Cotizaciones\Pages;

use App\Enums\EstadoCotizacion;
use App\Filament\App\Resources\Cotizaciones\CotizacionResource;
use App\Models\Cotizacion;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CrearCotizacion extends CreateRecord
{
    protected static string $resource = CotizacionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $empresa = Filament::getTenant();
        $data['numero'] = (int) Cotizacion::query()->withoutGlobalScopes()
            ->where('empresa_id', $empresa->id)->max('numero') + 1;
        $data['estado'] = EstadoCotizacion::Borrador->value;
        $data['user_id'] = Filament::auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
