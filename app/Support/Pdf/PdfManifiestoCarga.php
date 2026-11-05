<?php

namespace App\Support\Pdf;

use App\Models\Empresa;
use App\Models\ManifiestoCarga;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Manifiesto electrónico de carga en PDF, para portar en el viaje (con QR verificable).
 * No reemplaza el formato oficial del RNDC; sirve como constancia interna hasta el radicado.
 */
final class PdfManifiestoCarga
{
    public static function pdf(ManifiestoCarga $manifiesto): string
    {
        $manifiesto->loadMissing(['origen.departamento', 'destino.departamento', 'municipioPago', 'vehiculo.marca', 'remolque',
            'conductor.tipoIdentificacion', 'titularPropietario.tipoIdentificacion', 'titularTercero.tipoIdentificacion',
            'remesas.remitente', 'remesas.destinatario', 'ultimoRegistroRndc']);
        $empresa = Empresa::query()->with('municipio')->findOrFail($manifiesto->empresa_id);

        $html = view('pdf.manifiesto-carga', [
            'm' => $manifiesto,
            'empresa' => $empresa,
            'titular' => $manifiesto->titular(),
            'logo' => Imagenes::deDisco($empresa->logo_path, 'public'),
            'qr' => Imagenes::qr($manifiesto->urlVerificacion(), 3),
            'retefuente' => (int) round((float) $manifiesto->valor_flete * (float) $manifiesto->retencion_fuente_pct / 100),
            'reteica' => (int) round((float) $manifiesto->valor_flete * (float) $manifiesto->reteica_por_mil / 1000),
        ])->render();

        return Pdf::loadHTML($html)->setPaper('letter')->setOption(['isFontSubsettingEnabled' => true, 'isRemoteEnabled' => false])->output();
    }

    public static function nombre(ManifiestoCarga $m): string
    {
        return 'manifiesto-' . $m->numeroFormateado() . '.pdf';
    }
}
