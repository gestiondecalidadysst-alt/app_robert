<?php

namespace App\Support\Operacion;

use Illuminate\Support\Carbon;

/**
 * Horario de un servicio: días (fecha inicial y final) y franja diaria (hora inicial y final).
 *
 * - Sin horas = todo el día (00:00 a 24:00).
 * - Si la hora final es menor o igual que la inicial, el servicio pasa la medianoche
 *   (p. ej. 18:00 a 06:00: cada día de 18:00 a las 06:00 del día siguiente).
 *
 * Se usa para no asignar el mismo vehículo o conductor a dos servicios que se cruzan.
 */
final class Horario
{
    private const DIA = 1440;

    private int $diaInicio;

    private int $diaFin;

    private int $desde;

    private int $hasta;

    public function __construct(
        public readonly Carbon $fechaInicio,
        public readonly Carbon $fechaFin,
        public readonly ?string $horaInicio = null,
        public readonly ?string $horaFin = null,
    ) {
        $this->diaInicio = (int) floor($fechaInicio->copy()->startOfDay()->timestamp / 86400);
        $this->diaFin = (int) floor($fechaFin->copy()->startOfDay()->timestamp / 86400);

        $this->desde = self::minutos($horaInicio) ?? 0;
        $hasta = self::minutos($horaFin);

        $this->hasta = match (true) {
            $hasta === null => self::DIA,
            $hasta <= $this->desde => $hasta + self::DIA, // pasa la medianoche
            default => $hasta,
        };
    }

    /** ¿Algún momento en común con otro horario? */
    public function seCruzaCon(self $otro): bool
    {
        $desde = max($this->diaInicio, $otro->diaInicio - 1);
        $hasta = min($this->diaFin, $otro->diaFin + 1);

        for ($d = $desde; $d <= $hasta; $d++) {
            [$a0, $a1] = $this->franja($d);

            foreach ([$d - 1, $d, $d + 1] as $e) {
                if ($e < $otro->diaInicio || $e > $otro->diaFin) {
                    continue;
                }
                [$b0, $b1] = $otro->franja($e);

                if (max($a0, $b0) < min($a1, $b1)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** "del 01/11/2026 al 05/11/2026, de 06:00 a 15:00" */
    public function texto(): string
    {
        $dias = $this->fechaInicio->isSameDay($this->fechaFin)
            ? 'el ' . $this->fechaInicio->format('d/m/Y')
            : 'del ' . $this->fechaInicio->format('d/m/Y') . ' al ' . $this->fechaFin->format('d/m/Y');

        return $dias . ', ' . $this->franjaTexto();
    }

    public function franjaTexto(): string
    {
        if ($this->horaInicio === null && $this->horaFin === null) {
            return 'todo el día';
        }

        return 'de ' . self::hhmm($this->horaInicio ?? '00:00') . ' a ' . self::hhmm($this->horaFin ?? '23:59')
            . ($this->hasta > self::DIA ? ' (del día siguiente)' : '');
    }

    /** @return array{0:int,1:int} minutos absolutos [inicio, fin) del servicio en el día $d */
    private function franja(int $d): array
    {
        return [$d * self::DIA + $this->desde, $d * self::DIA + $this->hasta];
    }

    private static function minutos(?string $hora): ?int
    {
        if (blank($hora) || ! preg_match('/^(\d{1,2}):(\d{2})/', $hora, $m)) {
            return null;
        }

        return (int) $m[1] * 60 + (int) $m[2];
    }

    private static function hhmm(string $hora): string
    {
        return substr($hora, 0, 5);
    }
}
