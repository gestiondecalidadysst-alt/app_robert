<?php

namespace App\Filament\App\Resources\Contratos\Pages;

use App\Actions\Operacion\CrearContrato;
use App\Filament\App\Resources\Contratos\ContratoResource;
use App\Models\Contrato;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class EditarContrato extends EditRecord
{
    protected static string $resource = ContratoResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        abort_if($this->getRecord()->anulado(), 403, 'El contrato está anulado.');
    }

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()];
    }

    public function getRelationManagers(): array
    {
        return [];
    }

    /** Las fechas nuevas deben seguir cubriendo los FUEC ya expedidos (art. 15). */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Contrato $record */
        CrearContrato::validarFechas($data);

        $fuera = $record->fuecs()->noAnulados()
            ->where(fn ($q) => $q->whereDate('fecha_inicio', '<', Carbon::parse($data['fecha_inicio']))
                ->orWhereDate('fecha_fin', '>', Carbon::parse($data['fecha_fin'])))
            ->count();

        if ($fuera > 0) {
            throw ValidationException::withMessages([
                'data.fecha_fin' => "Hay {$fuera} FUEC fuera de las fechas nuevas. Anúlelos o amplíe la vigencia.",
            ]);
        }

        return parent::handleRecordUpdate($record, $data);
    }

    protected function getRedirectUrl(): ?string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
