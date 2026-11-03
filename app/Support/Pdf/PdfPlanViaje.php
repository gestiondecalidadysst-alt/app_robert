<?php

namespace App\Support\Pdf;

use App\Actions\Pesv\GuardarFirmaConductor;
use App\Models\Empresa;
use App\Models\Ruta;
use App\Models\Servicio;
use App\Support\Operacion\Jornada;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Plan de viaje (PESV paso 15) para llevar a bordo: datos del servicio, rutograma (puntos críticos,
 * paradas seguras, velocidades), requisitos para iniciar y finalizar, medidas contra fatiga,
 * velocidad y distracción, y la constancia de lectura del conductor.
 * rutograma(): solo la ruta, sin servicio.
 */
final class PdfPlanViaje
{
    public static function servicio(Servicio $servicio): string
    {
        $servicio->loadMissing(['ruta', 'vehiculo', 'conductores', 'origen', 'destino', 'contrato.cliente', 'fuec', 'leidoPor']);

        return self::generar([
            'servicio' => $servicio,
            'ruta' => $servicio->ruta,
            'avisos' => $servicio->cancelado() ? [] : Jornada::avisos($servicio, $servicio->conductores->pluck('id')->all()),
            'firma' => $servicio->leidoPor ? Imagenes::deDisco($servicio->leidoPor->firma_path, GuardarFirmaConductor::DISCO) : null,
        ], $servicio->empresa_id);
    }

    public static function rutograma(Ruta $ruta): string
    {
        $ruta->loadMissing(['origen', 'destino']);

        return self::generar(['servicio' => null, 'ruta' => $ruta, 'avisos' => [], 'firma' => null], $ruta->empresa_id);
    }

    public static function nombre(Servicio $servicio): string
    {
        return 'plan-de-viaje-' . $servicio->fecha_inicio->format('Y-m-d') . '-' . ($servicio->placa ?? 'sin-vehiculo') . '.pdf';
    }

    private static function generar(array $datos, int $empresaId): string
    {
        $empresa = Empresa::query()->with('municipio')->findOrFail($empresaId);

        $html = view('pdf.plan-viaje', $datos + [
            'empresa' => $empresa,
            'logo' => Imagenes::deDisco($empresa->logo_path, 'public'),
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('letter')->setOption(['isFontSubsettingEnabled' => true, 'isRemoteEnabled' => false]);
        $dompdf = $pdf->getDomPDF();
        $dompdf->render();
        $canvas = $dompdf->getCanvas();
        $canvas->page_text($canvas->get_width() - 100, $canvas->get_height() - 26, 'Página {PAGE_NUM} / {PAGE_COUNT}', $dompdf->getFontMetrics()->getFont('DejaVu Sans'), 7, [0.3, 0.3, 0.3]);

        return $dompdf->output();
    }
}
