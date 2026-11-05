<?php

namespace App\Filament\App\Resources\Servicios\Pages;

use App\Actions\Operacion\PlanificarServicio;
use App\Filament\App\Resources\Servicios\Schemas\ServicioForm;
use App\Filament\App\Resources\Servicios\ServicioResource;
use App\Models\Servicio;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditarServicio extends EditRecord
{
    protected static string $resource = ServicioResource::class;

    protected static ?string $title = 'Editar plan del servicio';

    public function mount(int|string $record): void
    {
        parent::mount($record);
        abort_if($this->getRecord()->cancelado(), 404);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return ServicioForm::aFormulario($this->getRecord());
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Servicio $record */
        try {
            return app(PlanificarServicio::class)->guardar(Filament::getTenant(), ServicioForm::aDatos($data), $record);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($m, $campo) => [
                'data.'.($campo === 'conductores' ? 'conductor_1' : $campo) => $m,
            ])->all());
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
