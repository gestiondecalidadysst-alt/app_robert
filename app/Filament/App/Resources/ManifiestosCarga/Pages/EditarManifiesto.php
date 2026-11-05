<?php
namespace App\Filament\App\Resources\ManifiestosCarga\Pages;
use App\Filament\App\Resources\ManifiestosCarga\ManifiestoCargaResource;
use App\Models\RemesaCarga;
use Filament\Resources\Pages\EditRecord;
class EditarManifiesto extends EditRecord
{
    protected static string $resource = ManifiestoCargaResource::class;

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
