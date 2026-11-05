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
        $manifiesto->loadMissing(['origen.departamento', 'destino.departamento', 'municipioIntermedio.departamento', 'municipioPago.departamento',
            'vehiculo.marca', 'vehiculo.propietario.tipoIdentificacion', 'vehiculo.propietario.municipio',
            'remolque', 'remolque2', 'conductor.tipoIdentificacion', 'conductor.municipio',
            'conductor2.tipoIdentificacion', 'conductor2.municipio',
            'titularPropietario.tipoIdentificacion', 'titularPropietario.municipio',
            'titularTercero.tipoIdentificacion', 'titularTercero.municipio',
            'remesas.remitente.municipio', 'remesas.destinatario.municipio', 'ultimoRegistroRndc']);
        $empresa = Empresa::query()->with('municipio')->findOrFail($manifiesto->empresa_id);

        // SOAT del vehículo (número y vencimiento de los documentos; NIT desde el campo propio del vehículo).
        $soat = $manifiesto->vehiculo?->documentoVigente(\App\Enums\TipoDocumentoVehiculo::Soat);
        $soat?->loadMissing('aseguradora');

        $html = view('pdf.manifiesto-carga', [
            'm' => $manifiesto,
            'empresa' => $empresa,
            'titular' => $manifiesto->titular(),
            'logoMinisterio' => self::oficial('ministerio-transporte.png'),
            'logoSuper' => self::oficial('supertransporte.png'),
            'logoEmpresa' => Imagenes::deDisco($empresa->logo_path, 'public'),
            'qr' => Imagenes::qr($manifiesto->urlVerificacion(), 4),
            'soat' => $soat,
            'retefuente' => $manifiesto->retefuente(),
            'reteica' => $manifiesto->reteica(),
            'retefopat' => $manifiesto->retefopat(),
            'valor_neto' => $manifiesto->valorNeto(),
            'valor_en_letras' => \App\Support\NumeroEnLetras::pesos((int) $manifiesto->valor_flete),
        ])->render();

        return Pdf::loadHTML($html)->setPaper('letter', 'landscape')
            ->setOption(['isFontSubsettingEnabled' => true, 'isRemoteEnabled' => false])->output();
    }

    /** Logo oficial puesto por el superadministrador en resources/images (null si aún no está). */
    private static function oficial(string $archivo): ?string
    {
        $ruta = resource_path('images/' . $archivo);

        return is_file($ruta) ? Imagenes::deArchivo($ruta) : null;
    }

    public static function nombre(ManifiestoCarga $m): string
    {
        return 'manifiesto-' . $m->numeroFormateado() . '.pdf';
    }
}
