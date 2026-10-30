<?php

namespace App\Filament\App\Resources\Contratos\Pages;

use App\Actions\Operacion\CrearContrato as AccionCrearContrato;
use App\Filament\App\Resources\Contratos\ContratoResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CrearContrato extends CreateRecord
{
    protected static string $resource = ContratoResource::class;

    protected static ?string $title = 'Nuevo contrato';

    protected function handleRecordCreation(array $data): Model
    {
        return app(AccionCrearContrato::class)(Filament::getTenant(), $data);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return "Contrato {$this->getRecord()->numero_formateado} creado";
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
