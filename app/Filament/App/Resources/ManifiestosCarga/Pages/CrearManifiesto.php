<?php
namespace App\Filament\App\Resources\ManifiestosCarga\Pages;
use App\Filament\App\Resources\ManifiestosCarga\ManifiestoCargaResource;
use App\Models\RemesaCarga;
use Filament\Resources\Pages\CreateRecord;
class CrearManifiesto extends CreateRecord
{
    protected static string $resource = ManifiestoCargaResource::class;

    /** El wizard trae su propio botón «Expedir manifiesto» en el último paso. */
    protected function getFormActions(): array
    {
        return [];
    }

    /** Al guardar: asocia las remesas elegidas (y suelta las que se quitaron). */
    protected function afterSave(): void
    {
        $ids = $this->data['remesas_ids'] ?? [];
        $record = $this->getRecord();
        RemesaCarga::query()->where('manifiesto_id', $record->id)->whereNotIn('id', $ids)->update(['manifiesto_id' => null]);
        RemesaCarga::query()->whereIn('id', $ids)->update(['manifiesto_id' => $record->id]);
    }

    protected function afterCreate(): void
    {
        $this->afterSave();
    }
}
