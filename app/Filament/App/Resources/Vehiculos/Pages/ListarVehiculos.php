<?php

namespace App\Filament\App\Resources\Vehiculos\Pages;

use App\Enums\EstadoVehiculo;
use App\Filament\App\Resources\Vehiculos\VehiculoResource;
use App\Models\Vehiculo;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListarVehiculos extends ListRecords
{
    protected static string $resource = VehiculoResource::class;

    public function getSubheading(): ?string
    {
        $conteo = Vehiculo::query()->toBase()->selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado');

        return collect(EstadoVehiculo::cases())
            ->filter(fn (EstadoVehiculo $e) => $e->enFlota())
            ->map(fn (EstadoVehiculo $e) => ($conteo[$e->value] ?? 0).' '.mb_strtolower($e->getLabel()))
            ->prepend($conteo->except(EstadoVehiculo::Desvinculado->value)->sum().' en flota')
            ->implode(' · ');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nuevo vehículo'),
        ];
    }
}
