<?php

namespace App\Filament\App\Resources\Vehiculos\Pages;

use App\Filament\App\Resources\Vehiculos\VehiculoResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditarVehiculo extends EditRecord
{
    protected static string $resource = VehiculoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->label('Hoja de vida'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return self::limpiarVinculacion($data);
    }

    /** La empresa en convenio solo aplica a la vinculación por convenio empresarial. */
    public static function limpiarVinculacion(array $data): array
    {
        if (($data['tipo_vinculacion'] ?? null) !== 'convenio_empresarial') {
            $data['empresa_convenio_id'] = null;
        }

        return $data;
    }

    public function getRelationManagers(): array
    {
        return []; // documentos y novedades se gestionan en la hoja de vida
    }

    protected function getRedirectUrl(): ?string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
