<?php

namespace App\Filament\App\Resources\Conductores\Pages;

use App\Filament\App\Resources\Conductores\ConductorResource;
use App\Filament\App\Resources\Conductores\Concerns\GuardaFirmaDelFormulario;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditarConductor extends EditRecord
{
    use GuardaFirmaDelFormulario;

    protected static string $resource = ConductorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->label('Hoja de vida'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->separarFirma($data);
    }

    protected function afterSave(): void
    {
        $this->guardarFirmaPendiente($this->getRecord());
    }

    public function getRelationManagers(): array
    {
        return []; // documentos, vehículos y novedades se gestionan en la hoja de vida
    }

    protected function getRedirectUrl(): ?string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
