<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Carbon;

/**
 * Estado calculado de un contrato o un FUEC según sus fechas y si fue anulado.
 */
enum EstadoVigencia: string implements HasColor, HasLabel
{
    case Programado = 'programado';
    case Vigente = 'vigente';
    case Vencido = 'vencido';
    case Anulado = 'anulado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Programado => 'Programado',
            self::Vigente => 'Vigente',
            self::Vencido => 'Terminado',
            self::Anulado => 'Anulado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Programado => 'info',
            self::Vigente => 'success',
            self::Vencido => 'gray',
            self::Anulado => 'danger',
        };
    }

    public static function para(\DateTimeInterface $inicio, \DateTimeInterface $fin, bool $anulado, ?\DateTimeInterface $hoy = null): self
    {
        $hoy = Carbon::instance($hoy ?? now())->startOfDay();

        return match (true) {
            $anulado => self::Anulado,
            $hoy->lt(Carbon::instance($inicio)->startOfDay()) => self::Programado,
            $hoy->gt(Carbon::instance($fin)->startOfDay()) => self::Vencido,
            default => self::Vigente,
        };
    }
}
