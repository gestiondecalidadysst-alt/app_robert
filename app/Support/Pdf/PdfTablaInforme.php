<?php

namespace App\Support\Pdf;

use App\Models\Empresa;
use App\Models\Oficina;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;

/**
 * Informe tabular en PDF con el aspecto estándar del sistema: encabezado con logo y empresa,
 * pie con fecha de generación y número de página, y la tabla de datos. Lo usan los informes
 * de gerencia y de operación para que todos se vean igual.
 *
 * Elige horizontal cuando hay muchas columnas, para que no queden ilegibles.
 */
final class PdfTablaInforme
{
    /**
     * @param  list<string>  $columnas
     * @param  list<list<mixed>>  $filas
     */
    public static function generar(
        int $empresaId,
        string $titulo,
        Carbon $desde,
        Carbon $hasta,
        ?int $oficinaId,
        array $columnas,
        array $filas,
    ): string {
        $empresa = Empresa::query()->with('municipio')->findOrFail($empresaId);
        $horizontal = count($columnas) > 8;

        $html = view('pdf.tabla-informe', [
            'empresa' => $empresa,
            'logo' => Imagenes::deDisco($empresa->logo_path, 'public'),
            'titulo' => $titulo,
            'desde' => $desde,
            'hasta' => $hasta,
            'oficina' => $oficinaId ? Oficina::query()->withoutGlobalScopes()->find($oficinaId)?->nombre : null,
            'columnas' => $columnas,
            'filas' => $filas,
            'horizontal' => $horizontal,
        ])->render();

        return Pdf::loadHTML($html)->setPaper('letter', $horizontal ? 'landscape' : 'portrait')
            ->setOption(['isRemoteEnabled' => false])->output();
    }

    public static function nombre(string $informe, Carbon $desde, Carbon $hasta): string
    {
        return "informe-{$informe}-{$desde->format('Y-m-d')}-a-{$hasta->format('Y-m-d')}.pdf";
    }
}
