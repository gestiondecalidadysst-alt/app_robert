<?php

namespace App\Filament\App\Resources\ManifiestosCarga\Pages\Concerns;

use App\Support\Carga\AsociadorDeRemesas;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;

/**
 * Asocia al manifiesto las remesas elegidas en el paso «Remesas» del asistente.
 *
 * El id viaja por el camino validado del formulario (`mutateFormDataBefore…`), no por
 * el estado crudo de Livewire: así no depende de que `$this->data` sobreviva a la
 * rehidratación que Filament hace al guardar. Si llega vacío se detiene el guardado,
 * porque un manifiesto sin remesa no es válido ante el RNDC.
 */
trait AsociaRemesas
{
    /** @var array<int>|null */
    protected ?array $remesasElegidas = null;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->apartarRemesas($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->apartarRemesas($data);
    }

    /**
     * Saca `remesas_ids` de los datos del modelo (no es una columna) y lo guarda aparte.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function apartarRemesas(array $data): array
    {
        $ids = $data['remesas_ids'] ?? $this->data['remesas_ids'] ?? [];
        $this->remesasElegidas = array_values(array_filter(array_map('intval', (array) $ids)));

        unset($data['remesas_ids']);

        if ($this->remesasElegidas === []) {
            Notification::make()->danger()
                ->title('Falta la remesa')
                ->body('Vuelva al paso «Remesas» y marque al menos una: el manifiesto no puede expedirse sin remesa.')
                ->persistent()->send();

            throw new Halt;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $asociadas = AsociadorDeRemesas::aplicar($this->getRecord(), $this->remesasElegidas ?? []);

        // Red de seguridad: si por lo que sea la asociación no quedó, se deshace todo.
        // Vale más no expedir el manifiesto que expedirlo vacío ante el RNDC.
        if ($asociadas === 0) {
            Notification::make()->danger()
                ->title('No se pudo asociar la remesa')
                ->body('El manifiesto no se expidió. La remesa puede estar anulada o ya ir en otro manifiesto; vuelva al paso «Remesas» y elija otra.')
                ->persistent()->send();

            throw (new Halt)->rollBackDatabaseTransaction();
        }
    }

    protected function afterCreate(): void
    {
        $this->afterSave();
    }
}
