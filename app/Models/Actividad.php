<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Spatie\Activitylog\Models\Activity;

/**
 * Registro de auditoría (quién cambió qué y cuándo), separado por empresa.
 */
class Actividad extends Activity
{
    use PerteneceAEmpresa;

    /** Nombre legible del evento. */
    public function getEventoLegibleAttribute(): string
    {
        return match ($this->event) {
            'created' => 'Creó',
            'updated' => 'Modificó',
            'deleted' => 'Eliminó',
            default => (string) $this->event,
        };
    }
}
