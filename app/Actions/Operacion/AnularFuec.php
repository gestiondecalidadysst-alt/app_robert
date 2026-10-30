<?php

namespace App\Actions\Operacion;

use App\Models\Fuec;
use Illuminate\Validation\ValidationException;

/**
 * Anula un FUEC con su motivo. El número no se reutiliza; la verificación pública lo muestra como anulado.
 */
class AnularFuec
{
    public function __invoke(Fuec $fuec, string $motivo): void
    {
        if ($fuec->anulado()) {
            throw ValidationException::withMessages(['motivo' => 'El FUEC ya está anulado.']);
        }

        if (blank(trim($motivo))) {
            throw ValidationException::withMessages(['motivo' => 'El motivo es obligatorio.']);
        }

        $fuec->forceFill([
            'anulado_at' => now(),
            'motivo_anulacion' => trim($motivo),
            'anulado_por' => auth()->id(),
        ])->save();
    }
}
