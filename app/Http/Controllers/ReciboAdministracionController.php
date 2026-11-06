<?php

namespace App\Http\Controllers;

use App\Models\PagoAdministracion;
use App\Support\Pdf\PdfReciboAdministracion;
use Illuminate\Http\Response;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga del recibo de pago de administración, para un usuario del panel con permiso y de la
 * misma empresa del recibo.
 */
class ReciboAdministracionController extends Controller
{
    public function __invoke(PagoAdministracion $pago): StreamedResponse
    {
        $usuario = auth()->user();

        // Los permisos son por empresa (equipos de Spatie): se fija la del recibo antes de evaluar.
        app(PermissionRegistrar::class)->setPermissionsTeamId($pago->empresa_id);

        abort_unless(
            $usuario
            && $usuario->empresas()->whereKey($pago->empresa_id)->exists()
            && $usuario->can('ver_pagos_administracion'),
            Response::HTTP_FORBIDDEN,
        );

        return response()->streamDownload(
            fn () => print (PdfReciboAdministracion::recibo($pago)),
            PdfReciboAdministracion::nombreRecibo($pago),
            ['Content-Type' => 'application/pdf'],
        );
    }
}
