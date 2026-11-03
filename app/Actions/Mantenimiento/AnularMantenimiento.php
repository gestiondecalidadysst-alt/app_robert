<?php

namespace App\Actions\Mantenimiento;

use App\Models\Mantenimiento;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** Un mantenimiento no se edita ni se borra: se anula con motivo (deja de contar para el preventivo). */
final class AnularMantenimiento
{
    public function __invoke(Mantenimiento $mantenimiento, string $motivo, ?User $usuario = null): void
    {
        if ($mantenimiento->anulado()) {
            throw ValidationException::withMessages(['motivo' => 'El mantenimiento ya está anulado.']);
        }
        if (blank($motivo)) {
            throw ValidationException::withMessages(['motivo' => 'Indique el motivo.']);
        }

        $mantenimiento->forceFill(['anulado_at' => now(), 'motivo_anulacion' => trim($motivo), 'anulado_por' => $usuario?->id])->save();
        ProgramaAnual::revertirEjecucion($mantenimiento, $usuario);
    }
}
