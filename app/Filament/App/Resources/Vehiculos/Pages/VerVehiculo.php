<?php

namespace App\Filament\App\Resources\Vehiculos\Pages;

use App\Filament\App\Resources\Vehiculos\Acciones\CambiarEstadoAction;
use App\Filament\App\Resources\Vehiculos\VehiculoResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\On;

/**
 * Hoja de vida del vehículo: resumen, semáforo de documentos, datos, y pestañas de
 * documentos (con historial) y novedades.
 */
class VerVehiculo extends ViewRecord
{
    protected static string $resource = VehiculoResource::class;

    public function getTitle(): string|Htmlable
    {
        return "Vehículo {$this->getRecord()->placa}";
    }

    protected function resolveRecord(int|string $key): \Illuminate\Database\Eloquent\Model
    {
        return parent::resolveRecord($key)->load([
            'marca', 'linea', 'claseVehiculo', 'carroceria', 'combustible', 'tipoServicio',
            'organismoTransito.departamento', 'propietario', 'empresaConvenio', 'documentos',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            CambiarEstadoAction::make()->after(fn () => $this->refrescar()),
            EditAction::make(),
        ];
    }

    /** Los documentos o el estado cambiaron desde una pestaña o acción: recarga el resumen. */
    #[On('documentos-actualizados')]
    public function refrescar(): void
    {
        $this->getRecord()->refresh()->load('documentos');
    }

    public function getContentTabLabel(): ?string
    {
        return 'Resumen';
    }

}
