<?php

namespace App\Services\Rndc;

use App\Models\Propietario;
use App\Support\Carga\CatalogoRndc as C;

/** Validaciones del diccionario de datos de Vehículos (proceso 12). */
final class ValidarVehiculo
{
    /** @return list<string> */
    public static function comunes(?string $placa, ?int $configuracion, ?string $marca, ?int $modelo, ?int $pesoVacio,
        ?int $capacidad, ?int $ejes, ?Propietario $propietario, ?Propietario $tenedor): array
    {
        $e = [];
        if (! $configuracion) {
            $e[] = 'Falta la configuración del vehículo (código del RNDC).';
        }
        if (blank($marca)) {
            $e[] = 'Falta el código RNDC de la marca.';
        }
        if (! $modelo || $modelo <= 1900 || $modelo > (int) date('Y') + 1) {
            $e[] = 'El año del modelo no es válido.';
        }
        if (! $pesoVacio || $pesoVacio <= 200 || $pesoVacio >= 53000) {
            $e[] = 'El peso vacío debe estar entre 201 y 52.999 kg.';
        }
        if ($configuracion && in_array($configuracion, C::CON_CAPACIDAD, true) && ($capacidad === null || $capacidad < 100 || $capacidad > 99999)) {
            $e[] = 'Para esta configuración la capacidad es obligatoria (3 a 5 dígitos).';
        }
        if ($configuracion && in_array($configuracion, C::CON_EJES, true) && ! $ejes) {
            $e[] = 'Para esta configuración el número de ejes es obligatorio.';
        }
        foreach (['propietario' => $propietario, 'tenedor' => $tenedor] as $rol => $p) {
            if (! $p) {
                $e[] = "Falta el {$rol} del vehículo.";
            } elseif (! C::tipoId($p->tipoIdentificacion?->codigo)) {
                $e[] = "El tipo de identificación del {$rol} no lo admite el RNDC.";
            }
        }

        return $e;
    }
}
