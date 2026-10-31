<?php

namespace App\Filament\App\Resources\PlantillasContrato\Pages;

use App\Actions\Contratos\GuardarPlantillaContrato;
use App\Filament\App\Resources\PlantillasContrato\PlantillaContratoResource;
use App\Models\Empresa;
use App\Support\Contratos\PlantillaBase;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Nueva versión de la plantilla: parte del texto vigente (o de la plantilla base).
 */
class NuevaVersion extends CreateRecord
{
    protected static string $resource = PlantillaContratoResource::class;

    protected static ?string $title = 'Nueva versión de la plantilla de contrato';

    protected static bool $canCreateAnother = false;

    protected function fillForm(): void
    {
        /** @var Empresa $empresa */
        $empresa = Filament::getTenant();
        $vigente = $empresa->plantillaContratoVigente();

        $this->form->fill([
            'codigo_formato' => $vigente?->codigo_formato,
            'version' => ($vigente?->version ?? 0) + 1,
            'titulo' => $vigente?->titulo ?? PlantillaBase::TITULO,
            'contenido' => $vigente?->contenido ?? PlantillaBase::contenido(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('restaurarBase')
                ->label('Volver al texto base')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Reemplaza el texto del editor por la plantilla base del sistema. Nada se guarda hasta que pulse «Guardar versión».')
                ->action(fn () => $this->form->fill([...$this->form->getRawState(), 'contenido' => PlantillaBase::contenido()])),
        ];
    }

    protected function handleRecordCreation(array $data): Model
    {
        return app(GuardarPlantillaContrato::class)(Filament::getTenant(), $data);
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Guardar versión');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return "Versión {$this->getRecord()->version_formateada} guardada. Es la vigente desde ahora.";
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
