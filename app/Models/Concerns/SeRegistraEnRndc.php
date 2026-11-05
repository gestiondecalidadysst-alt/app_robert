<?php

namespace App\Models\Concerns;

use App\Models\RegistroRndc;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/** Historial de registros en el RNDC y el último exitoso. */
trait SeRegistraEnRndc
{
    public function registrosRndc(): MorphMany
    {
        return $this->morphMany(RegistroRndc::class, 'registrable');
    }

    public function ultimoRegistroRndc(): MorphOne
    {
        return $this->morphOne(RegistroRndc::class, 'registrable')->latestOfMany();
    }

    /** registrado | contingencia | error | desactualizado | pendiente */
    public function estadoRndc(): string
    {
        $ultimo = $this->relationLoaded('ultimoRegistroRndc') ? $this->ultimoRegistroRndc : $this->ultimoRegistroRndc()->first();
        if (! $ultimo) {
            return 'pendiente';
        }
        if ($ultimo->estado === 'contingencia') {
            return 'contingencia';
        }

        if ($ultimo->estado === 'error') {
            return 'error';
        }

        return $this->updated_at && $this->updated_at->gt($ultimo->created_at) ? 'desactualizado' : 'registrado';
    }

    public static function etiquetaEstadoRndc(string $estado): string
    {
        return ['pendiente' => 'Sin registrar', 'registrado' => 'Registrado', 'error' => 'Con errores',
            'contingencia' => 'En espera: el RNDC no respondió', 'desactualizado' => 'Cambió: volver a registrar'][$estado] ?? $estado;
    }

    public static function colorEstadoRndc(string $estado): string
    {
        return ['pendiente' => 'gray', 'registrado' => 'success', 'error' => 'danger',
            'contingencia' => 'info', 'desactualizado' => 'warning'][$estado] ?? 'gray';
    }
}
