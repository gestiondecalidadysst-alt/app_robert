<?php

namespace App\Support\Mantenimiento;

use App\Models\Empresa;
use App\Models\RutinaMantenimiento;

/**
 * Rutinas preventivas por defecto (aprobadas por el usuario el 2026-11-01). Son valores de
 * referencia: cada empresa las ajusta al manual del fabricante en Mantenimiento → Rutinas preventivas.
 */
final class RutinasBase
{
    /** [nombre, km, meses] */
    public const RUTINAS = [
        ['Aceite de motor y filtro de aceite', 5000, 3],
        ['Filtro de combustible', 10000, 6],
        ['Filtro de aire', 20000, 12],
        ['Revisión de frenos (pastillas, bandas, líquido)', 10000, 6],
        ['Alineación, balanceo y rotación de llantas', 10000, 6],
        ['Suspensión y dirección', 20000, 12],
        ['Refrigerante y mangueras', 40000, 24],
        ['Aceite de caja y diferencial', 40000, 24],
        ['Correa de distribución o accesorios', 60000, null],
        ['Batería y sistema eléctrico', 20000, 12],
    ];

    public static function crear(Empresa $empresa): void
    {
        if (RutinaMantenimiento::query()->deEmpresa($empresa)->exists()) {
            return;
        }

        foreach (self::RUTINAS as $i => [$nombre, $km, $meses]) {
            $rutina = new RutinaMantenimiento([
                'nombre' => $nombre,
                'intervalo_km' => $km,
                'intervalo_meses' => $meses,
                'orden' => ($i + 1) * 10,
                'descripcion' => $nombre === 'Correa de distribución o accesorios' ? 'Por tiempo: según el manual del fabricante.' : null,
            ]);
            $rutina->empresa_id = $empresa->id;
            $rutina->save();
        }
    }
}
