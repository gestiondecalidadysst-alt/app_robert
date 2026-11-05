<?php

namespace App\Actions\Operacion;

use App\Actions\Contratos\AnularDocumentoContrato;
use App\Models\Contrato;
use Illuminate\Validation\ValidationException;

/**
 * Anula un contrato. No se permite si tiene FUEC sin anular: primero se anulan los extractos.
 */
class AnularContrato
{
    public function __invoke(Contrato $contrato, string $motivo): void
    {
        if ($contrato->anulado()) {
            throw ValidationException::withMessages(['motivo' => 'El contrato ya está anulado.']);
        }

        if (blank(trim($motivo))) {
            throw ValidationException::withMessages(['motivo' => 'El motivo es obligatorio.']);
        }

        $activos = $contrato->fuecs()->noAnulados()->count();
        if ($activos > 0) {
            throw ValidationException::withMessages([
                'motivo' => "El contrato tiene {$activos} FUEC sin anular. Anúlelos primero.",
            ]);
        }

        // El documento del contrato (si lo hay) también queda anulado: su enlace de firma deja de servir.
        foreach ($contrato->documentos()->activos()->get() as $documento) {
            app(AnularDocumentoContrato::class)($documento, 'Contrato anulado: '.trim($motivo));
        }

        $contrato->forceFill([
            'anulado_at' => now(),
            'motivo_anulacion' => trim($motivo),
            'anulado_por' => auth()->id(),
        ])->save();
    }
}
