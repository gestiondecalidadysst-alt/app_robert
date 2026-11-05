<?php

namespace App\Filament\App\Resources\Conductores\Acciones;

use App\Actions\Conductores\CambiarEstadoConductor;
use App\Enums\TipoNovedadConductor;
use App\Filament\Compartido\ErroresDeNegocio;
use App\Models\Conductor;
use App\Models\NovedadConductor;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Carbon;

/**
 * Acción "Cambiar estado" del conductor (suspensión, retiro, reactivación, reintegro).
 */
final class CambiarEstadoConductorAction
{
    public static function make(): Action
    {
        return Action::make('cambiarEstado')
            ->label('Cambiar estado')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->authorize(fn () => auth()->user()->can('create', NovedadConductor::class))
            ->modalHeading(fn (Conductor $record) => "Cambiar estado de {$record->nombre_completo}")
            ->modalDescription(fn (Conductor $record) => "Estado actual: {$record->estado->getLabel()}. La novedad queda en el historial. El retiro termina sus asignaciones de vehículo.")
            ->modalSubmitActionLabel('Registrar novedad')
            ->schema(fn (Conductor $record) => [
                Select::make('tipo')
                    ->label('Novedad')
                    ->options(collect(TipoNovedadConductor::disponiblesDesde($record->estado))
                        ->mapWithKeys(fn (TipoNovedadConductor $t) => [$t->value => $t->getLabel()]))
                    ->required()
                    ->live(),
                DatePicker::make('fecha')->label('Fecha')->default(now())->maxDate(now())->required(),
                DatePicker::make('fecha_reactivacion')
                    ->label('Reactivación prevista')
                    ->minDate(now()->startOfDay())
                    ->visible(fn (Get $get) => TipoNovedadConductor::tryFrom((string) $get('tipo'))?->pideReactivacion() ?? false),
                Textarea::make('observacion')->label('Observación')->required()->rows(3)->maxLength(2000),
            ])
            ->action(function (Conductor $record, array $data, Action $action): void {
                $novedad = ErroresDeNegocio::intentar($action, fn () => app(CambiarEstadoConductor::class)(
                    $record,
                    TipoNovedadConductor::from($data['tipo']),
                    $data['observacion'],
                    Carbon::parse($data['fecha']),
                    filled($data['fecha_reactivacion'] ?? null) ? Carbon::parse($data['fecha_reactivacion']) : null,
                ));

                Notification::make()
                    ->title("{$record->nombre_completo}: {$novedad->estado_nuevo->getLabel()}")
                    ->success()
                    ->send();
            });
    }
}
