<?php

namespace App\Filament\App\Resources\Fuecs\Pages;

use App\Actions\Operacion\ExpedirFuec as AccionExpedirFuec;
use App\Filament\App\Resources\Fuecs\FuecResource;
use App\Filament\App\Resources\Fuecs\Schemas\FuecForm;
use App\Models\Fuec;
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

    protected function fillForm(): void
    {
        $this->form->fill(FuecForm::valoresDeContrato(request()->query('contrato')));
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(AccionExpedirFuec::class)(Filament::getTenant(), FuecForm::aDatos($data));
        } catch (ValidationException $e) {
            // Los mensajes se ubican en los campos del formulario.
            $errores = collect($e->errors())->mapWithKeys(fn ($mensajes, $campo) => [
                'data.' . ($campo === 'conductores' ? 'conductor_1' : $campo) => $mensajes,
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

    protected function getCreateFormAction(): \Filament\Actions\Action
    {
        return parent::getCreateFormAction()->label('Expedir FUEC');
    }
}
