<?php

namespace App\Filament\App\Resources\Fuecs\Acciones;

use App\Actions\Operacion\AnularFuec;
use App\Filament\Compartido\ErroresDeNegocio;
use App\Models\Fuec;
use App\Support\Pdf\PdfFuec;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;

/**
 * Acciones de un FUEC: descargar el PDF y anular.
 */
final class AccionesFuec
{
    public static function pdf(): Action
    {
        return Action::make('pdf')
            ->label('Descargar PDF')
            ->icon('heroicon-o-arrow-down-tray')
            ->action(fn (Fuec $record) => response()->streamDownload(
                fn () => print (PdfFuec::generar($record)),
                PdfFuec::nombreArchivo($record),
                ['Content-Type' => 'application/pdf'],
            ));
    }

    public static function anular(): Action
    {
        return Action::make('anular')
            ->label('Anular')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Fuec $record) => ! $record->anulado())
            ->authorize(fn (Fuec $record) => auth()->user()->can('anular', $record))
            ->requiresConfirmation()
            ->modalHeading(fn (Fuec $record) => "Anular FUEC {$record->numero}")
            ->modalDescription('El número no se reutiliza. Al escanear el QR, el documento aparecerá como anulado.')
            ->schema([Textarea::make('motivo')->label('Motivo')->required()->rows(2)])
            ->action(fn (Fuec $record, array $data, Action $action) => ErroresDeNegocio::intentar(
                $action,
                fn () => app(AnularFuec::class)($record, $data['motivo']),
            ))
            ->successNotificationTitle('FUEC anulado');
    }
}
