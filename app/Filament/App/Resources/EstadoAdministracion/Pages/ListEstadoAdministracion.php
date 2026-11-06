<?php

namespace App\Filament\App\Resources\EstadoAdministracion\Pages;

use App\Actions\Administracion\GenerarCuotasDelMes;
use App\Filament\App\Resources\EstadoAdministracion\EstadoAdministracionResource;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;

class ListEstadoAdministracion extends ListRecords
{
    protected static string $resource = EstadoAdministracionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generar')
                ->label('Generar cuotas del mes')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->visible(fn () => Filament::auth()->user()?->can('crear_pagos_administracion'))
                ->requiresConfirmation()
                ->modalHeading('Generar las cuotas de '.ucfirst(now()->translatedFormat('F Y')))
                ->modalDescription('Crea la cuota de este mes para cada vehículo en flota que tenga valor (ajuste propio o el de su clase). No duplica las que ya existan, así que es seguro repetirlo.')
                ->modalSubmitActionLabel('Generar')
                ->action(function (): void {
                    $resultado = app(GenerarCuotasDelMes::class)(Filament::getTenant(), Carbon::now());

                    $cuerpo = $resultado['creadas'] === 0
                        ? 'No había cuotas nuevas por crear este mes.'
                        : "{$resultado['creadas']} cuota(s) creada(s).";
                    if ($resultado['omitidos'] > 0) {
                        $cuerpo .= " {$resultado['omitidos']} vehículo(s) sin valor de administración (defina su clase o un ajuste).";
                    }

                    Notification::make()
                        ->title('Cuotas de administración del mes')
                        ->body($cuerpo)
                        ->success()
                        ->send();
                }),
        ];
    }
}
