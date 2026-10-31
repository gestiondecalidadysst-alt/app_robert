<?php

namespace App\Support\Pdf;

use App\Models\Contrato;
use App\Models\DocumentoContrato;
use App\Models\Empresa;
use App\Models\PlantillaContrato;
use App\Support\Contratos\RedactorContrato;
use App\Support\Contratos\VariablesContrato;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * PDF del contrato: encabezado y pie fijos en cada página (con márgenes que evitan que se monten
 * sobre el texto), numeración "Página X / Y", bloque de firmas y, si está firmado, el
 * certificado de firma electrónica con el registro de evidencias.
 */
final class PdfContrato
{
    /** PDF de un documento emitido (sin firmar, o el firmado al momento de firmar). */
    public static function documento(DocumentoContrato $documento): string
    {
        $documento->loadMissing(['eventos', 'contrato']);
        $carpeta = DocumentoContrato::DISCO;

        return self::generar([
            'contenido' => $documento->contenido,
            'empresa' => $documento->datos['empresa'] ?? [],
            'valores' => $documento->datos['valores'] ?? [],
            'logo' => Imagenes::deDisco($documento->datos['archivos']['logo'] ?? null, $carpeta),
            'pie' => Imagenes::deDisco($documento->datos['archivos']['pie'] ?? null, $carpeta),
            'firmaContratista' => Imagenes::deDisco($documento->firma_contratista_path, $carpeta),
            'firmaContratante' => $documento->firmado() ? Imagenes::deDisco($documento->firma_path, $carpeta) : null,
            'documento' => $documento,
            'qr' => Imagenes::qr($documento->urlVerificacion(), 3),
            'marca' => $documento->anulado() ? 'ANULADO' : null,
        ]);
    }

    /** Vista previa antes de emitir: plantilla vigente con los datos actuales; marca "VISTA PREVIA". */
    public static function vistaPrevia(Contrato $contrato, PlantillaContrato $plantilla, Empresa $empresa): string
    {
        $redaccion = RedactorContrato::componer($contrato, $plantilla, $empresa);

        return self::generar([
            'contenido' => $redaccion['html'],
            'empresa' => RedactorContrato::datosEmpresa($empresa, $plantilla),
            'valores' => $redaccion['valores'] + VariablesContrato::valores($contrato, $empresa),
            'logo' => Imagenes::deDisco($empresa->logo_path, 'public'),
            'pie' => Imagenes::deDisco($empresa->pie_documentos_path, 'public'),
            'firmaContratista' => null,
            'firmaContratante' => null,
            'documento' => null,
            'qr' => null,
            'marca' => 'VISTA PREVIA',
        ]);
    }

    private static function generar(array $datos): string
    {
        $html = view('pdf.contrato', $datos)->render();

        $pdf = Pdf::loadHTML($html)
            ->setPaper('letter')
            ->setOption(['isFontSubsettingEnabled' => true, 'isRemoteEnabled' => false]);

        $dompdf = $pdf->getDomPDF();
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $fuente = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'bold');
        $canvas->page_text($canvas->get_width() - 112, $canvas->get_height() - 30, 'Página {PAGE_NUM} / {PAGE_COUNT}', $fuente, 7.5, [0.2, 0.2, 0.2]);

        return $dompdf->output();
    }
}
