<?php

namespace App\Filament\App\Resources\ItemsChequeo\Pages;

use App\Enums\TipoInspeccion;
use App\Filament\App\Resources\ItemsChequeo\ItemChequeoResource;
use App\Models\ItemChequeo;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageItemsChequeo extends ManageRecords
{
    protected static string $resource = ItemChequeoResource::class;

    public function getSubheading(): ?string
    {
        return 'Arrastre para cambiar el orden. Los críticos que no cumplen dejan el vehículo NO APTO. Los cambios aplican a las inspecciones nuevas.';
    }

    public function getTabs(): array
    {
        return collect(TipoInspeccion::cases())->mapWithKeys(fn (TipoInspeccion $tipo) => [
            $tipo->value => Tab::make($tipo->corto())
                ->badge(ItemChequeo::query()->deTipo($tipo)->activos()->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('tipo', $tipo->value)),
        ])->all();
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return TipoInspeccion::Preoperacional->value;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateDataUsing(function (array $data): array {
                    $data['orden'] = (int) ItemChequeo::query()->where('tipo', $data['tipo'] instanceof TipoInspeccion ? $data['tipo']->value : $data['tipo'])->max('orden') + 10;

                    return $data;
                }),
        ];
    }
}
