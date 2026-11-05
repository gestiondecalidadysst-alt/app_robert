<?php

namespace App\Filament\App\Carga;

use App\Models\Empresa;
use App\Models\ManifiestoCarga;
use App\Models\Municipio;
use App\Models\RemesaCarga;
use App\Services\Rndc\AnulableRndc;
use App\Services\Rndc\Rndc;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Anulación de remesas (proceso 9) y manifiestos (proceso 32).
 *
 * Anula aquí y, si el documento está radicado y la conexión activa, lo reporta al RNDC
 * en la misma operación. Si el RNDC rechaza la anulación no se anula nada localmente:
 * tener aquí un documento anulado que ante el Ministerio sigue vivo es peor que no anularlo.
 */
final class AnularConRndc
{
    public static function remesa(): Action
    {
        return Action::make('anular')->label('Anular')->icon('heroicon-o-x-circle')->color('danger')
            ->modalHeading('Anular remesa')
            ->modalSubmitActionLabel('Anular')
            ->visible(fn (RemesaCarga $r) => auth()->user()?->can('anular_remesas_carga') && ! $r->anulado())
            ->schema(fn (RemesaCarga $r) => [
                Placeholder::make('aviso')->hiddenLabel()
                    ->content(self::aviso($r)),
                Select::make('reversa')->label('Qué hacer')->required()->native(false)
                    ->options([
                        'A' => 'Anular la remesa',
                        'L' => 'Liberarla del manifiesto (transbordo)',
                    ])
                    ->default($r->manifiesto_id ? 'L' : 'A')
                    ->disabled(fn () => ! $r->manifiesto_id)
                    ->dehydrated()
                    ->live()
                    ->helperText($r->manifiesto_id
                        ? 'Va en un manifiesto: el RNDC solo permite liberarla para un transbordo.'
                        : 'No está en ningún manifiesto, así que puede anularse.'),
                Select::make('motivo')->label('Motivo')->required()->native(false)
                    ->options(RemesaCarga::MOTIVOS_ANULACION)->default('D'),
                Select::make('municipio_transbordo_id')->label('Municipio del transbordo')->searchable()
                    ->visible(fn ($get) => $get('reversa') === 'L')
                    ->required(fn ($get) => $get('reversa') === 'L')
                    ->getSearchResultsUsing(fn (string $search) => Municipio::query()
                        ->where('nombre', 'like', "%{$search}%")->orderBy('nombre')->limit(50)
                        ->get()->mapWithKeys(fn (Municipio $m) => [$m->id => $m->nombre_completo]))
                    ->getOptionLabelUsing(fn ($value) => Municipio::find($value)?->nombre_completo)
                    ->placeholder('Escriba el nombre del municipio…'),
                Select::make('motivo_transbordo')->label('Causa del transbordo')->native(false)
                    ->options(RemesaCarga::MOTIVOS_TRANSBORDO)->default('V')
                    ->visible(fn ($get) => $get('reversa') === 'L')
                    ->required(fn ($get) => $get('reversa') === 'L'),
                Textarea::make('observacion')->label('Observación interna')->rows(2)->maxLength(200),
            ])
            ->action(fn (RemesaCarga $r, array $data) => self::ejecutar($r, $data));
    }

    public static function manifiesto(): Action
    {
        return Action::make('anular')->label('Anular')->icon('heroicon-o-x-circle')->color('danger')
            ->modalHeading('Anular manifiesto')
            ->modalSubmitActionLabel('Anular')
            ->visible(fn (ManifiestoCarga $m) => auth()->user()?->can('anular_manifiestos_carga') && ! $m->anulado())
            ->schema(fn (ManifiestoCarga $m) => [
                Placeholder::make('aviso')->hiddenLabel()->content(self::aviso($m)),
                Select::make('motivo')->label('Motivo')->required()->native(false)
                    ->options(ManifiestoCarga::MOTIVOS_ANULACION)->default('D'),
                Textarea::make('observacion')->label('Observación interna')->rows(2)->maxLength(200),
            ])
            ->action(fn (ManifiestoCarga $m, array $data) => self::ejecutar($m, $data));
    }

    /** Explica de antemano qué va a pasar, para que nadie anule a ciegas. */
    private static function aviso(AnulableRndc&Model $documento): string
    {
        $empresa = Empresa::find($documento->empresa_id);

        if ($documento->estadoRndc() === 'pendiente') {
            return 'No está radicado en el RNDC: se anula solo en el sistema.';
        }

        if (! $empresa || ! Rndc::conectado($empresa)) {
            return 'Está radicado en el RNDC, pero la conexión no está activa. Se anulará aquí y deberá anularlo también en la web del RNDC.';
        }

        return 'Está radicado en el RNDC: la anulación se reportará al Ministerio en esta misma operación.';
    }

    /** @param array<string, mixed> $datos */
    private static function ejecutar(AnulableRndc&Model $documento, array $datos): void
    {
        $empresa = Empresa::find($documento->empresa_id);
        $radicado = $documento->estadoRndc() !== 'pendiente';
        $conectado = $empresa && Rndc::conectado($empresa);
        $liberar = ($datos['reversa'] ?? 'A') === 'L';

        if ($radicado && $conectado) {
            try {
                $registro = app(Rndc::class)->anular($documento, $datos);
            } catch (ValidationException $e) {
                Notification::make()->danger()->title('El RNDC no permite anularlo')
                    ->body(implode(' ', $e->validator->errors()->all()))->persistent()->send();

                return;
            }

            if ($registro->estado === 'error') {
                // No se anula aquí: quedaría anulado en el sistema y vigente en el Ministerio.
                Notification::make()->danger()->title('El RNDC rechazó la anulación')
                    ->body(implode(' ', $registro->errores ?? ['Revise la bitácora RNDC.']))->persistent()->send();

                return;
            }
        }

        if ($liberar) {
            // Transbordo: la remesa no se anula, solo sale del manifiesto para ir en otro.
            $documento->update(['manifiesto_id' => null]);

            Notification::make()->success()->title('Remesa liberada')
                ->body('Ya puede asociarla al manifiesto del segundo trayecto.')->send();

            return;
        }

        $documento->update([
            'anulado_at' => now(),
            'motivo_anulacion' => $datos['motivo'] ?? null,
            'observaciones' => $documento->observaciones,
        ]);

        $cuerpo = match (true) {
            $radicado && $conectado => 'Anulada también en el RNDC.',
            $radicado => 'Recuerde anularlo también en la web del RNDC.',
            default => 'No estaba radicado en el RNDC.',
        };

        Notification::make()->success()->title('Anulado')->body($cuerpo)->send();
    }
}
