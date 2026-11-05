<?php

namespace App\Filament\App\Resources\Fuecs\Pages;

use App\Actions\Operacion\ExpedirFuec as AccionExpedirFuec;
use App\Filament\App\Resources\Fuecs\FuecResource;
use App\Filament\App\Resources\Fuecs\Schemas\FuecForm;
use App\Models\Fuec;
use App\Models\Servicio;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Expedir FUEC. Toda la validación está en App\Actions\Operacion\ExpedirFuec.
 */
class ExpedirFuec extends CreateRecord
{
    protected static string $resource = FuecResource::class;

    protected static ?string $title = 'Expedir FUEC';

    protected static bool $canCreateAnother = false;

    /** Servicio del planeador desde el que se genera el extracto (?servicio=). */
    public ?int $servicioId = null;

    protected function fillForm(): void
    {
        $servicio = request()->query('servicio')
            ? Servicio::query()->vigentes()->whereNull('fuec_id')->with('conductores')->find(request()->query('servicio'))
            : null;

        if (! $servicio) {
            $this->form->fill(FuecForm::valoresDeContrato(request()->query('contrato')));

            return;
        }

        // Datos del plan: contrato (si tiene), vehículo, conductores, fechas, horas, origen, destino y recorrido.
        $this->servicioId = $servicio->id;
        $valores = FuecForm::valoresDeContrato($servicio->contrato_id);
        $conductores = $servicio->conductores->pluck('id')->values();

        $this->form->fill(array_merge($valores, array_filter([
            'vehiculo_id' => $servicio->vehiculo_id,
            'fecha_inicio' => max($servicio->fecha_inicio->toDateString(), today()->toDateString()),
            'fecha_fin' => $servicio->fecha_fin->toDateString(),
            'hora_inicio' => $servicio->hora_salida ? substr((string) $servicio->hora_salida, 0, 5) : null,
            'hora_fin' => $servicio->hora_llegada ? substr((string) $servicio->hora_llegada, 0, 5) : null,
            'origen_municipio_id' => $servicio->origen_municipio_id,
            'destino_municipio_id' => $servicio->destino_municipio_id,
            'recorrido' => $servicio->recorrido ?: ($valores['recorrido'] ?? null),
        ], fn ($v) => $v !== null), collect(FuecForm::CAMPOS_CONDUCTOR)->mapWithKeys(fn ($campo, $i) => [$campo => $conductores[$i] ?? null])->filter()->all()));
    }

    public function getSubheading(): ?string
    {
        return $this->servicioId ? 'Desde el planeador: los datos vienen del servicio planificado; revíselos antes de expedir.' : null;
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(AccionExpedirFuec::class)(Filament::getTenant(), FuecForm::aDatos($data) + ['servicio_id' => $this->servicioId]);
        } catch (ValidationException $e) {
            // Los mensajes se ubican en los campos del formulario.
            $errores = collect($e->errors())->mapWithKeys(fn ($mensajes, $campo) => [
                'data.'.($campo === 'conductores' ? 'conductor_1' : $campo) => $mensajes,
            ])->all();

            throw ValidationException::withMessages($errores);
        }
    }

    protected function getCreatedNotification(): ?Notification
    {
        /** @var Fuec $fuec */
        $fuec = $this->getRecord();

        return Notification::make()
            ->success()
            ->title('FUEC expedido')
            ->body("No. {$fuec->numero} · {$fuec->placa}");
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Expedir FUEC');
    }
}
