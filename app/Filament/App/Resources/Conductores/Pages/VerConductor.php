<?php

namespace App\Filament\App\Resources\Conductores\Pages;

use App\Filament\App\Resources\Conductores\Acciones\CambiarEstadoConductorAction;
use App\Filament\App\Resources\Conductores\ConductorResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;

/**
 * Hoja de vida del conductor.
 */
class VerConductor extends ViewRecord
{
    protected static string $resource = ConductorResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->nombre_completo;
    }

    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->load(self::RELACIONES);
    }

    private const RELACIONES = [
        'tipoIdentificacion', 'municipio.departamento', 'nivelAcademico', 'oficina', 'eps', 'arl',
        'documentos.categoriaLicencia', 'asignacionesVigentes.vehiculo',
    ];

    protected function getHeaderActions(): array
    {
        return [
            CambiarEstadoConductorAction::make()->after(fn () => $this->refrescar()),
            EditAction::make(),
        ];
    }

    /** Documentos, asignaciones o estado cambiaron: recarga el resumen. */
    #[On('documentos-actualizados')]
    public function refrescar(): void
    {
        $this->getRecord()->refresh()->load(self::RELACIONES);
    }

    public function getContentTabLabel(): ?string
    {
        return 'Resumen';
    }
}
