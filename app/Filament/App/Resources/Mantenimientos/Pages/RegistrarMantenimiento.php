<?php

namespace App\Filament\App\Resources\Mantenimientos\Pages;

use App\Actions\Mantenimiento\RegistrarMantenimiento as AccionRegistrar;
use App\Filament\App\Resources\Mantenimientos\MantenimientoResource;
use App\Models\PlanAccion;
use App\Models\Vehiculo;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Registrar mantenimiento. Toda la validación está en App\Actions\Mantenimiento\RegistrarMantenimiento.
 * Acepta ?vehiculo=ID&plan=ID para llegar desde un plan de acción o la hoja de vida.
 */
class RegistrarMantenimiento extends CreateRecord
{
    protected static string $resource = MantenimientoResource::class;

    protected static ?string $title = 'Registrar mantenimiento';

    protected static bool $canCreateAnother = false;

    protected function fillForm(): void
    {
        $plan = request()->integer('plan') ? PlanAccion::query()->pendientes()->find(request()->integer('plan')) : null;
        $vehiculo = $plan?->vehiculo_id ?? (request()->integer('vehiculo') ?: null);

        $this->form->fill(array_filter([
            'fecha' => today()->toDateString(),
            'vehiculo_id' => $vehiculo,
            'plan_accion_id' => $plan?->id,
            'tipo' => $plan ? ($plan->origen->value === 'preventivo' ? 'preventivo' : 'correctivo') : null,
            'rutinas' => $plan?->rutina_mantenimiento_id ? [$plan->rutina_mantenimiento_id] : null,
            'problema' => $plan ? collect($plan->hallazgos ?? [])->map(fn ($h) => $h['descripcion'] . (($h['observacion'] ?? null) ? ': ' . $h['observacion'] : ''))->implode("\n") : null,
        ], fn ($v) => $v !== null));
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(AccionRegistrar::class)(
                Filament::getTenant(),
                Vehiculo::query()->findOrFail($data['vehiculo_id']),
                $data,
                array_values(array_filter((array) ($data['archivos'] ?? []))),
                auth()->user(),
            );
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($m, $campo) => ['data.' . $campo => $m])->all());
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getCreateFormAction(): \Filament\Actions\Action
    {
        return parent::getCreateFormAction()->label('Registrar');
    }
}
