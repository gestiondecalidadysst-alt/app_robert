<?php

namespace App\Filament\App\Acciones;

use App\Models\Conductor;
use App\Models\Vehiculo;
use App\Support\Pdf\PdfHojaVidaConductor;
use App\Support\Pdf\PdfHojaVidaVehiculo;
use App\Support\Pdf\SeccionHojaVida;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Carbon;

/**
 * Descarga de la hoja de vida (vehículo o conductor) en PDF, con el usuario eligiendo qué
 * secciones incluir y el rango de fechas para las que dependen de un periodo.
 */
final class HojaVidaPdf
{
    public static function vehiculo(): Action
    {
        return self::accion(SeccionHojaVida::VEHICULO)
            ->action(function (Vehiculo $record, array $data) {
                [$desde, $hasta] = self::rango($data);
                $pdf = PdfHojaVidaVehiculo::generar($record, $data['secciones'], $desde, $hasta);

                return response()->streamDownload(fn () => print ($pdf), PdfHojaVidaVehiculo::nombre($record), ['Content-Type' => 'application/pdf']);
            });
    }

    public static function conductor(): Action
    {
        return self::accion(SeccionHojaVida::CONDUCTOR)
            ->action(function (Conductor $record, array $data) {
                [$desde, $hasta] = self::rango($data);
                $pdf = PdfHojaVidaConductor::generar($record, $data['secciones'], $desde, $hasta);

                return response()->streamDownload(fn () => print ($pdf), PdfHojaVidaConductor::nombre($record), ['Content-Type' => 'application/pdf']);
            });
    }

    /**
     * @param  array<string, string>  $secciones
     */
    private static function accion(array $secciones): Action
    {
        $conRango = fn (Get $get): bool => (bool) array_intersect($get('secciones') ?? [], SeccionHojaVida::CON_RANGO);

        return Action::make('hoja_vida_pdf')
            ->label('Hoja de vida (PDF)')
            ->icon('heroicon-o-document-arrow-down')
            ->modalHeading('Descargar hoja de vida')
            ->modalDescription('Elija qué incluir. Las secciones por periodo (servicios, preoperacionales, mantenimientos, novedades) usan el rango de fechas.')
            ->modalSubmitActionLabel('Descargar PDF')
            ->schema([
                CheckboxList::make('secciones')
                    ->label('Secciones a incluir')
                    ->options($secciones)
                    ->default(array_keys($secciones))
                    ->columns(2)
                    ->required()
                    ->live(),
                DatePicker::make('desde')->label('Desde')->native(false)->default(now()->startOfYear())
                    ->visible($conRango)->required($conRango),
                DatePicker::make('hasta')->label('Hasta')->native(false)->default(now())
                    ->visible($conRango)->required($conRango)
                    ->afterOrEqual('desde'),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: Carbon, 1: Carbon}
     */
    private static function rango(array $data): array
    {
        return [
            filled($data['desde'] ?? null) ? Carbon::parse($data['desde']) : now()->startOfYear(),
            filled($data['hasta'] ?? null) ? Carbon::parse($data['hasta']) : now(),
        ];
    }
}
