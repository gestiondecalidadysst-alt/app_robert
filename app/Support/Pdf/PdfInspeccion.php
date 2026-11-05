<?php

namespace App\Support\Pdf;

use App\Models\Inspeccion;
use App\Models\Vehiculo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * PDF de un preoperacional o inspección mensual (con la firma copiada del conductor y QR de
 * verificación) e informe mensual del preoperacional de un vehículo (matriz ítems × días).
 */
final class PdfInspeccion
{
    public static function generar(Inspeccion $inspeccion): string
    {
        $html = view('pdf.inspeccion', [
            'i' => $inspeccion,
            'd' => $inspeccion->datos,
            'grupos' => collect($inspeccion->respuestas)->groupBy('grupo'),
            'logo' => Imagenes::deDisco($inspeccion->datos['empresa']['logo_path'] ?? null, 'public'),
            'firma' => Imagenes::deDisco($inspeccion->firma_path, Inspeccion::DISCO),
            'qr' => Imagenes::qr($inspeccion->urlVerificacion()),
        ])->render();

        return self::pdf($html, 'portrait');
    }

    /**
     * Informe del preoperacional de un vehículo en un rango de hasta 31 días: una columna por día
     * con lo que respondió cada ítem, el conductor, el kilometraje y el resultado.
     *
     * @param  Collection<int, Inspeccion>  $inspecciones  del vehículo en el rango
     */
    public static function informe(Vehiculo $vehiculo, Carbon $desde, Carbon $hasta, Collection $inspecciones, array $empresa): string
    {
        $dias = collect();
        for ($d = $desde->copy(); $d->lte($hasta); $d->addDay()) {
            $dias->push($d->copy());
        }

        // Por día cuenta la última inspección (si hubo una no apta y luego una apta, la apta).
        $porDia = $inspecciones->sortBy('id')->keyBy(fn (Inspeccion $i) => $i->fecha->toDateString());

        // Filas: los ítems tal como se respondieron (en orden de aparición), incluidos los que ya no existen.
        $filas = $inspecciones->sortBy('id')->flatMap(fn (Inspeccion $i) => $i->respuestas)
            ->unique(fn (array $r) => $r['grupo'].'|'.$r['descripcion'])->values();

        // Firma de cada conductor del período (la copia guardada en su último preoperacional del rango).
        $firmas = $inspecciones->sortByDesc('id')
            ->filter(fn (Inspeccion $i) => filled($i->datos['conductor']['documento'] ?? null))
            ->groupBy(fn (Inspeccion $i) => $i->datos['conductor']['documento'])
            ->map(fn ($grupo) => [
                'nombre' => $grupo->first()->datos['conductor']['nombre'] ?? '',
                'documento' => $grupo->first()->datos['conductor']['documento'],
                'dias' => $grupo->map(fn (Inspeccion $i) => $i->fecha->format('d'))->unique()->sort()->values()->implode(', '),
                'firma' => Imagenes::deDisco($grupo->first(fn (Inspeccion $i) => filled($i->firma_path))?->firma_path, Inspeccion::DISCO),
            ])->values();

        $html = view('pdf.informe-preoperacional', [
            'firmas' => $firmas,
            'vehiculo' => $vehiculo,
            'desde' => $desde,
            'hasta' => $hasta,
            'dias' => $dias,
            'porDia' => $porDia,
            'filas' => $filas,
            'inspecciones' => $inspecciones->sortBy(['fecha', 'id']),
            'e' => $empresa,
            'logo' => Imagenes::deDisco($empresa['logo_path'] ?? null, 'public'),
        ])->render();

        return self::pdf($html, 'landscape');
    }

    public static function nombreArchivo(Inspeccion $inspeccion): string
    {
        $tipo = $inspeccion->tipo->value === 'mensual' ? 'Inspeccion' : 'Preoperacional';

        return "{$tipo}-{$inspeccion->placa}-{$inspeccion->fecha->format('Y-m-d')}-{$inspeccion->id}.pdf";
    }

    private static function pdf(string $html, string $orientacion): string
    {
        return Pdf::loadHTML($html)
            ->setPaper('letter', $orientacion)
            ->setOption(['isFontSubsettingEnabled' => true, 'isRemoteEnabled' => false])
            ->output();
    }
}
