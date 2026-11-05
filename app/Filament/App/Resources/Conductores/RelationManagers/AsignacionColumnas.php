<?php

namespace App\Filament\App\Resources\Conductores\RelationManagers;

use App\Actions\Conductores\AsignarVehiculo;
use App\Filament\Compartido\ErroresDeNegocio;
use App\Models\AsignacionVehiculo;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

/**
 * Columnas y acción "Terminar" compartidas por las pestañas de asignación
 * (Vehículos del conductor y Conductores del vehículo).
 */
final class AsignacionColumnas
{
    /** @param  'vehiculo'|'conductor'  $mostrar  Qué lado de la asignación se muestra. */
    public static function comunes(string $mostrar): array
    {
        $principal = $mostrar === 'vehiculo'
            ? TextColumn::make('vehiculo.placa')
                ->label('Vehículo')
                ->formatStateUsing(fn (?string $state) => new HtmlString('<span class="ruta-placa">'.e($state).'</span>'))
                ->description(fn (AsignacionVehiculo $r) => $r->vehiculo?->descripcion)
            : TextColumn::make('conductor.nombres')
                ->label('Conductor')
                ->formatStateUsing(fn (AsignacionVehiculo $r) => $r->conductor?->nombre_completo)
                ->description(fn (AsignacionVehiculo $r) => $r->conductor?->telefono)
                ->weight('bold');

        return [
            $principal,
            IconColumn::make('principal')->boolean()->trueIcon('heroicon-o-star')->falseIcon(null)->trueColor('warning'),
            TextColumn::make('fecha_desde')->label('Desde')->date('d/m/Y'),
            TextColumn::make('fecha_hasta')->label('Hasta')->date('d/m/Y')->placeholder('Vigente'),
            TextColumn::make('observacion')->label('Observación')->placeholder('—')->limit(60)->visibleFrom('lg'),
        ];
    }

    public static function terminar(?Closure $despues = null): Action
    {
        return Action::make('terminar')
            ->label('Terminar')
            ->icon('heroicon-o-stop-circle')
            ->color('danger')
            ->visible(fn (AsignacionVehiculo $r) => $r->vigente())
            ->authorize(fn (AsignacionVehiculo $r) => auth()->user()->can('update', $r))
            ->modalHeading('Terminar asignación')
            ->modalSubmitActionLabel('Terminar')
            ->schema([
                DatePicker::make('fecha_hasta')->label('Hasta')->default(now())->required(),
            ])
            ->action(function (AsignacionVehiculo $r, array $data, Action $action) use ($despues): void {
                ErroresDeNegocio::intentar($action, fn () => app(AsignarVehiculo::class)->terminar($r, Carbon::parse($data['fecha_hasta'])));
                $despues && $despues();
            })
            ->successNotificationTitle('Asignación terminada');
    }
}
