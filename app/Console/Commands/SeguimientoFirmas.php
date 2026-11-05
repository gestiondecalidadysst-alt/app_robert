<?php

namespace App\Console\Commands;

use App\Actions\Contratos\EnviarParaFirma;
use App\Enums\EstadoFirma;
use App\Enums\ModuloEmpresa;
use App\Models\DocumentoContrato;
use App\Models\Empresa;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Throwable;

/**
 * Seguimiento diario de contratos pendientes de firma:
 * - Recordatorio (una vez) a quien no ha firmado después de los días configurados.
 * - Aviso a quien emitió cuando el enlace venció sin firma.
 */
class SeguimientoFirmas extends Command
{
    protected $signature = 'ruta:firmas';

    protected $description = 'Envía recordatorios de firma y avisa de enlaces vencidos';

    public function handle(): int
    {
        $pendientes = DocumentoContrato::query()->withoutGlobalScopes()
            ->whereIn('estado', [EstadoFirma::Enviado->value, EstadoFirma::Visto->value])
            ->get();

        $recordatorios = 0;
        $vencidos = 0;

        foreach ($pendientes as $documento) {
            $empresa = Empresa::find($documento->empresa_id);

            if (! $empresa?->activa || ! $empresa->tieneModulo(ModuloEmpresa::Pasajeros)) {
                continue;
            }

            if ($documento->token_expira_at?->isPast()) {
                if (! $documento->vencimiento_avisado_at) {
                    $this->avisarVencido($documento);
                    $vencidos++;
                }

                continue;
            }

            $dias = (int) $empresa->firma_dias_recordatorio;
            if ($dias > 0 && ! $documento->recordatorio_enviado_at && $documento->enviado_at?->lte(now()->subDays($dias))) {
                try {
                    app(EnviarParaFirma::class)($documento, [
                        'nombre' => $documento->firmante_nombre,
                        'documento' => $documento->firmante_documento,
                        'celular' => $documento->firmante_celular,
                        'email' => $documento->firmante_email,
                    ], recordatorio: true);
                    $recordatorios++;
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }

        $this->info("Recordatorios: {$recordatorios}. Enlaces vencidos avisados: {$vencidos}.");

        return self::SUCCESS;
    }

    private function avisarVencido(DocumentoContrato $documento): void
    {
        $documento->forceFill(['vencimiento_avisado_at' => now()])->save();
        $documento->registrar('vencido');

        if ($usuario = User::find($documento->user_id)) {
            $numero = $documento->datos['valores']['contrato.numero'] ?? '';

            Notification::make()
                ->warning()
                ->title("El enlace de firma del contrato {$numero} venció")
                ->body('El cliente no firmó a tiempo. Puede reenviarlo desde la pestaña «Documento y firma».')
                ->sendToDatabase($usuario);
        }
    }
}
