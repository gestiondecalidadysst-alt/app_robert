<?php

namespace App\Http\Controllers;

use App\Support\Pdf\PdfManual;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga del manual completo en PDF. Solo para el superadministrador de la plataforma.
 */
class ManualPdfController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        abort_unless((bool) auth()->user()?->es_superadmin, Response::HTTP_FORBIDDEN);

        return response()->streamDownload(
            fn () => print (PdfManual::completo()),
            PdfManual::nombre(),
            ['Content-Type' => 'application/pdf'],
        );
    }
}
