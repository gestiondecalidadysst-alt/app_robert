<?php

namespace App\Actions\Contratos;

use App\Enums\EstadoFirma;
use App\Models\DocumentoContrato;
use Illuminate\Validation\ValidationException;

/**
 * Anula el documento del contrato (con motivo). El enlace de firma deja de funcionar y la
 * verificación pública lo muestra como anulado. Después se puede emitir uno nuevo.
 */
class AnularDocumentoContrato
{
    public function __invoke(DocumentoContrato $documento, string $motivo): void
    {
        if ($documento->anulado()) {
            throw ValidationException::withMessages(['motivo' => 'El documento ya está anulado.']);
        }

        if (blank(trim($motivo))) {
            throw ValidationException::withMessages(['motivo' => 'El motivo es obligatorio.']);
        }

        $documento->forceFill([
            'estado' => EstadoFirma::Anulado,
            'token_hash' => null,
            'token_cifrado' => null,
            'anulado_at' => now(),
            'motivo_anulacion' => trim($motivo),
            'anulado_por' => auth()->id(),
        ])->save();

        $documento->registrar('anulado', ['motivo' => trim($motivo)]);
    }
}
