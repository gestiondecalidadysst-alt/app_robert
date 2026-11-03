<?php

namespace App\Filament\App\Resources\Servicios\Pages;

use App\Actions\Operacion\PlanificarServicio;
use App\Filament\App\Resources\Servicios\Schemas\ServicioForm;
use App\Filament\App\Resources\Servicios\ServicioResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CrearServicio extends CreateRecord
{
    protected static string $resource = ServicioResource::class;

    protected static ?string $title = 'Planificar servicio';

    protected static bool $canCreateAnother = false;

    protected function fillForm(): void
    {
        // Desde el tablero: ?fecha=2026-11-05&vehiculo=3 (o &conductor=7)
        $this->form->fill(array_filter([
            'fecha_inicio' => request()->query('fecha', today()->toDateString()),
            'vehiculo_id' => request()->query('vehiculo'),
            'conductor_1' => request()->query('conductor'),
        ]));
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(PlanificarServicio::class)->guardar(Filament::getTenant(), ServicioForm::aDatos($data));
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($m, $campo) => [
                'data.' . ($campo === 'conductores' ? 'conductor_1' : $campo) => $m,
            ])->all());
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getCreateFormAction(): \Filament\Actions\Action
    {
        return parent::getCreateFormAction()->label('Guardar plan');
    }
}
