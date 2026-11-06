<?php

namespace App\Support\Pdf;

use App\Models\Empresa;
use App\Models\Inspeccion;
use App\Models\Mantenimiento;
use App\Models\NovedadVehiculo;
use App\Models\PlanAccion;
use App\Models\Servicio;
use App\Models\Vehiculo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;

/**
 * Hoja de vida del vehículo en PDF, con las secciones que el usuario elija.
 *
 * Cada sección se arma solo si fue pedida. El blade pone encabezado y pie fijos y marca
 * cada bloque con page-break-inside para que una ficha o una fila no quede partida entre
 * dos páginas. Las secciones con rango (servicios, preoperacionales, mantenimientos,
 * novedades) se acotan a las fechas elegidas; las demás reflejan el estado actual.
 */
final class PdfHojaVidaVehiculo
{
    /**
     * @param  list<string>  $secciones  claves de SeccionHojaVida::VEHICULO
     */
    public static function generar(Vehiculo $vehiculo, array $secciones, Carbon $desde, Carbon $hasta): string
    {
        $vehiculo->loadMissing([
            'marca', 'linea', 'claseVehiculo', 'carroceria', 'combustible', 'tipoServicio',
            'propietario', 'tenedor', 'organismoTransito', 'empresaConvenio',
        ]);

        $empresa = Empresa::query()->with('municipio')->findOrFail($vehiculo->empresa_id);
        $pedidas = array_values(array_intersect(array_keys(SeccionHojaVida::VEHICULO), $secciones));

        $html = view('pdf.hoja-vida-vehiculo', [
            'v' => $vehiculo,
            'empresa' => $empresa,
            'logo' => Imagenes::deDisco($empresa->logo_path, 'public'),
            'desde' => $desde,
            'hasta' => $hasta,
            'secciones' => $pedidas,
            'etiquetas' => SeccionHojaVida::VEHICULO,
            'datos' => self::datos($vehiculo, $pedidas, $desde, $hasta),
        ])->render();

        return Pdf::loadHTML($html)->setPaper('letter', 'portrait')
            ->setOption(['isRemoteEnabled' => false])->output();
    }

    /**
     * Carga los datos de cada sección pedida.
     *
     * @param  list<string>  $secciones
     * @return array<string, mixed>
     */
    private static function datos(Vehiculo $vehiculo, array $secciones, Carbon $desde, Carbon $hasta): array
    {
        $datos = [];

        if (in_array('documentos', $secciones, true)) {
            $datos['documentos'] = $vehiculo->documentos()->with('aseguradora')
                ->orderBy('tipo')->orderByDesc('fecha_vencimiento')->get();
        }

        if (in_array('conductores', $secciones, true)) {
            $datos['conductores'] = $vehiculo->asignaciones()->with('conductor')
                ->orderByDesc('fecha_desde')->get();
        }

        if (in_array('servicios', $secciones, true)) {
            $datos['servicios'] = Servicio::query()->deEmpresa($vehiculo->empresa_id)
                ->where('vehiculo_id', $vehiculo->id)
                ->whereBetween('fecha_inicio', [$desde->toDateString(), $hasta->toDateString()])
                ->with(['contrato', 'ruta', 'conductores'])
                ->orderByDesc('fecha_inicio')->get();
        }

        if (in_array('preoperacionales', $secciones, true)) {
            $datos['preoperacionales'] = Inspeccion::query()->deEmpresa($vehiculo->empresa_id)
                ->where('vehiculo_id', $vehiculo->id)
                ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
                ->with(['conductor', 'fuec.contrato'])
                ->orderByDesc('fecha')->orderByDesc('id')->get();
        }

        if (in_array('mantenimientos', $secciones, true)) {
            $datos['mantenimientos'] = Mantenimiento::query()->deEmpresa($vehiculo->empresa_id)->reales()
                ->where('vehiculo_id', $vehiculo->id)
                ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
                ->with('rutinas')->orderByDesc('fecha')->get();
        }

        if (in_array('novedades', $secciones, true)) {
            $datos['novedades'] = NovedadVehiculo::query()->deEmpresa($vehiculo->empresa_id)
                ->where('vehiculo_id', $vehiculo->id)
                ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
                ->orderByDesc('fecha')->get();
        }

        if (in_array('planes', $secciones, true)) {
            $datos['planes'] = PlanAccion::query()->deEmpresa($vehiculo->empresa_id)
                ->where('vehiculo_id', $vehiculo->id)
                ->orderByDesc('fecha_limite')->get();
        }

        return $datos;
    }

    public static function nombre(Vehiculo $vehiculo): string
    {
        return 'hoja-de-vida-'.str($vehiculo->placa)->slug().'.pdf';
    }
}
