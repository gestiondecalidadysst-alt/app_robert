<?php

namespace App\Support\Pdf;

use App\Models\Conductor;
use App\Models\Empresa;
use App\Models\Inspeccion;
use App\Models\Servicio;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;

/**
 * Hoja de vida del conductor en PDF, con las secciones que el usuario elija.
 *
 * Misma mecánica que la del vehículo: encabezado y pie fijos, bloques que no se parten
 * entre páginas, y rango de fechas para las secciones por periodo.
 */
final class PdfHojaVidaConductor
{
    /**
     * @param  list<string>  $secciones  claves de SeccionHojaVida::CONDUCTOR
     */
    public static function generar(Conductor $conductor, array $secciones, Carbon $desde, Carbon $hasta): string
    {
        $conductor->loadMissing(['tipoIdentificacion', 'municipio', 'eps', 'arl', 'nivelAcademico', 'oficina']);

        $empresa = Empresa::query()->with('municipio')->findOrFail($conductor->empresa_id);
        $pedidas = array_values(array_intersect(array_keys(SeccionHojaVida::CONDUCTOR), $secciones));

        $html = view('pdf.hoja-vida-conductor', [
            'c' => $conductor,
            'empresa' => $empresa,
            'logo' => Imagenes::deDisco($empresa->logo_path, 'public'),
            'desde' => $desde,
            'hasta' => $hasta,
            'secciones' => $pedidas,
            'datos' => self::datos($conductor, $pedidas, $desde, $hasta),
        ])->render();

        return Pdf::loadHTML($html)->setPaper('letter', 'portrait')
            ->setOption(['isRemoteEnabled' => false])->output();
    }

    /**
     * @param  list<string>  $secciones
     * @return array<string, mixed>
     */
    private static function datos(Conductor $conductor, array $secciones, Carbon $desde, Carbon $hasta): array
    {
        $datos = [];

        if (in_array('documentos', $secciones, true)) {
            $datos['documentos'] = $conductor->documentos()->with('categoriaLicencia')
                ->orderBy('tipo')->orderByDesc('fecha_vencimiento')->get();
        }

        if (in_array('vehiculos', $secciones, true)) {
            $datos['vehiculos'] = $conductor->asignaciones()->with('vehiculo')
                ->orderByDesc('fecha_desde')->get();
        }

        if (in_array('servicios', $secciones, true)) {
            $datos['servicios'] = Servicio::query()->deEmpresa($conductor->empresa_id)
                ->whereBetween('fecha_inicio', [$desde->toDateString(), $hasta->toDateString()])
                ->where(fn ($q) => $q->whereHas('conductores', fn ($c) => $c->whereKey($conductor->id))
                    ->orWhere('conductor_id', $conductor->id))
                ->with(['contrato', 'ruta', 'vehiculo'])
                ->orderByDesc('fecha_inicio')->get();
        }

        if (in_array('preoperacionales', $secciones, true)) {
            $datos['preoperacionales'] = Inspeccion::query()->deEmpresa($conductor->empresa_id)
                ->where('conductor_id', $conductor->id)
                ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
                ->with(['vehiculo', 'fuec.contrato'])
                ->orderByDesc('fecha')->orderByDesc('id')->get();
        }

        if (in_array('novedades', $secciones, true)) {
            $datos['novedades'] = $conductor->novedades()
                ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
                ->orderByDesc('fecha')->get();
        }

        return $datos;
    }

    public static function nombre(Conductor $conductor): string
    {
        return 'hoja-de-vida-'.str($conductor->documento)->slug().'.pdf';
    }
}
