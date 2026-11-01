<?php

namespace App\Filament\App\Resources\Conductores\Pages;

use App\Filament\App\Resources\Conductores\ConductorResource;
use App\Filament\App\Resources\Conductores\Concerns\GuardaFirmaDelFormulario;
use Filament\Resources\Pages\CreateRecord;

class CrearConductor extends CreateRecord
{
    use GuardaFirmaDelFormulario;

    protected static string $resource = ConductorResource::class;

    protected static ?string $title = 'Nuevo conductor';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->separarFirma($data);
    }

    protected function afterCreate(): void
    {
        $this->guardarFirmaPendiente($this->getRecord());
    }

    /** Después de crear se abre la hoja de vida, para registrar la licencia y la seguridad social. */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
