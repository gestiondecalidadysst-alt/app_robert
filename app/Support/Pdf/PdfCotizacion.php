<?php

namespace App\Support\Pdf;

use App\Models\Cotizacion;
use App\Models\Empresa;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * PDF de la cotización de transporte especial, con la marca y los textos configurables de la empresa.
 * Reproduce la estructura: encabezado (marca y número), destinatario, asunto, tabla
 * Fecha/Ruta/Duración/Valor, descuento y total, bloques "incluye" y "equipo", validez y pie.
 */
final class PdfCotizacion
{
    public static function generar(Cotizacion $cotizacion): string
    {
        $cotizacion->loadMissing(['items', 'cliente', 'empresa.municipio']);
        $empresa = Empresa::query()->with('municipio')->findOrFail($cotizacion->empresa_id);
        $config = $empresa->cotizacionConfig();

        $html = view('pdf.cotizacion', [
            'c' => $cotizacion,
            'empresa' => $empresa,
            'config' => $config,
            'logo' => Imagenes::deDisco($empresa->logo_path, 'public'),
            'sello' => Imagenes::deDisco($config['sello_path'], 'public'),
            'certificados' => collect($config['certificados'])
                ->map(fn ($ruta) => Imagenes::deDisco($ruta, 'public'))->filter()->values()->all(),
        ])->render();

        return Pdf::loadHTML($html)->setPaper('letter')
            ->setOption(['isRemoteEnabled' => false, 'isFontSubsettingEnabled' => true])->output();
    }

    public static function nombre(Cotizacion $cotizacion): string
    {
        return 'cotizacion-'.$cotizacion->numero_formateado.'.pdf';
    }
}
