<?php

namespace App\Filament\App\Resources\Vehiculos\Acciones;

use App\Actions\Flota\CambiarEstadoVehiculo;
use App\Enums\TipoNovedadVehiculo;
use App\Models\NovedadVehiculo;
use App\Models\Vehiculo;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Carbon;

/**
 * Acción "Cambiar estado": registra una novedad (suspensión, taller, desvinculación,
 * reactivación o vinculación) con App\Actions\Flota\CambiarEstadoVehiculo.
 */
final class CambiarEstadoAction
{
    public static function make(): Action
    {
        return Action::make('cambiarEstado')
            ->label('Cambiar estado')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->authorize(fn () => auth()->user()->can('create', NovedadVehiculo::class))
            ->modalHeading(fn (Vehiculo $record) => "Cambiar estado de {$record->placa}")
            ->modalDescription(fn (Vehiculo $record) => "Estado actual: {$record->estado->getLabel()}. La novedad queda en el historial del vehículo.")
            ->modalSubmitActionLabel('Registrar novedad')
            ->schema(fn (Vehiculo $record) => [
                Select::make('tipo')
                    ->label('Novedad')
                    ->options(collect(TipoNovedadVehiculo::disponiblesDesde($record->estado))
                        ->mapWithKeys(fn (TipoNovedadVehiculo $t) => [$t->value => $t->getLabel()]))
                    ->required()
                    ->live(),
                DatePicker::make('fecha')
                    ->label('Fecha')
                    ->default(now())
                    ->maxDate(now())
                    ->required(),
                DatePicker::make('fecha_reactivacion')
                    ->label('Reactivación prevista')
                    ->minDate(now()->startOfDay())
                    ->visible(fn (Get $get) => TipoNovedadVehiculo::tryFrom((string) $get('tipo'))?->pideReactivacion() ?? false),
                Textarea::make('observacion')
                    ->label('Observación')
                    ->required()
                    ->rows(3)
                    ->maxLength(2000),
            ])
            ->action(function (Vehiculo $record, array $data): void {
                $novedad = app(CambiarEstadoVehiculo::class)(
                    $record,
                    TipoNovedadVehiculo::from($data['tipo']),
                    $data['observacion'],
                    Carbon::parse($data['fecha']),
                    filled($data['fecha_reactivacion'] ?? null) ? Carbon::parse($data['fecha_reactivacion']) : null,
                );

                Notification::make()
                    ->title("{$record->placa}: {$novedad->estado_nuevo->getLabel()}")
                    ->success()
                    ->send();
            });
    }
}
