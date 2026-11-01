<?php

namespace App\Filament\App\Resources\RutinasMantenimiento\Pages;

use App\Filament\App\Resources\RutinasMantenimiento\RutinaMantenimientoResource;
use App\Models\RutinaMantenimiento;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class GestionarRutinas extends ManageRecords
{
    protected static ?string $title = 'Rutinas preventivas';

    protected static string $resource = RutinaMantenimientoResource::class;

    public function getSubheading(): ?string
    {
        return 'Valores de referencia: ajústelos al manual del fabricante. Una rutina vencida abre un plan de acción (no bloquea el FUEC).';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->mutateDataUsing(function (array $data): array {
                $data['orden'] = (int) RutinaMantenimiento::query()->max('orden') + 10;
                if (filled($data['vehiculo_id'] ?? null)) {
                    $data['clase_vehiculo_id'] = null;
                }

                return $data;
            }),
        ];
    }
}
