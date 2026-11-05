<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Carbon;

/**
 * Semáforo de un documento según su fecha de vencimiento.
 * Los días de aviso se configuran en config('ruta.dias_aviso_vencimiento').
 */
enum EstadoDocumento: string implements HasColor, HasLabel
{
    case Vigente = 'vigente';
    case PorVencer = 'por_vencer';
    case Vencido = 'vencido';
    case Faltante = 'faltante';

    public function getLabel(): string
    {
        return match ($this) {
            self::Vigente => 'Vigente',
            self::PorVencer => 'Por vencer',
            self::Vencido => 'Vencido',
            self::Faltante => 'Sin registrar',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Vigente => 'success',
            self::PorVencer => 'warning',
            self::Vencido, self::Faltante => 'danger',
        };
    }

    /**
     * Estado de un documento que vence en $vencimiento, visto en la fecha $hoy (por defecto hoy).
     * $diasAviso: días antes del vencimiento en que pasa a "por vencer" (por defecto la configuración).
     */
    public static function paraVencimiento(?\DateTimeInterface $vencimiento, ?\DateTimeInterface $hoy = null, ?int $diasAviso = null): self
    {
        if ($vencimiento === null) {
            return self::Faltante;
        }

        $hoy = Carbon::instance($hoy ?? now())->startOfDay();
        $vence = Carbon::instance($vencimiento)->startOfDay();

        return match (true) {
            $vence->lt($hoy) => self::Vencido,
            $vence->lte($hoy->copy()->addDays($diasAviso ?? self::diasAvisoPorDefecto())) => self::PorVencer,
            default => self::Vigente,
        };
    }

    public static function diasAvisoPorDefecto(): int
    {
        return (int) config('ruta.dias_aviso_vencimiento', 30);
    }

    /** Gravedad para ordenar: el peor estado de varios documentos es el de mayor gravedad. */
    public function gravedad(): int
    {
        return match ($this) {
            self::Vigente => 0,
            self::PorVencer => 1,
            self::Vencido => 2,
            self::Faltante => 3,
        };
    }

    /** Impide operar el vehículo. */
    public function bloquea(): bool
    {
        return $this === self::Vencido || $this === self::Faltante;
    }
}
