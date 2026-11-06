<?php

namespace App\Support\Pdf;

use App\Models\CuotaAdministracion;
use App\Models\Empresa;
use App\Models\PagoAdministracion;
use App\Support\NumeroEnLetras;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Recibo de caja del pago de administración de un vehículo (media carta horizontal).
 * Muestra a qué meses se aplicó el pago y el saldo de administración que le queda al vehículo.
 */
final class PdfReciboAdministracion
{
    public static function recibo(PagoAdministracion $pago): string
    {
        $pago->loadMissing(['vehiculo.propietario', 'usuario', 'cuotas' => fn ($q) => $q->orderBy('periodo')]);
        $empresa = Empresa::query()->with('municipio')->findOrFail($pago->empresa_id);
        $vehiculo = $pago->vehiculo;

        // Saldo de administración que le queda al vehículo hoy (todas sus cuotas con saldo).
        $pendiente = CuotaAdministracion::query()->deEmpresa($empresa)
            ->where('vehiculo_id', $vehiculo?->id)
            ->whereNull('anulado_at')
            ->get()
            ->sum(fn (CuotaAdministracion $c) => $c->saldo());

        $html = view('pdf.recibo-administracion', [
            'pago' => $pago,
            'empresa' => $empresa,
            'vehiculo' => $vehiculo,
            'propietario' => $vehiculo?->propietario,
            'logo' => Imagenes::deDisco($empresa->logo_path, 'public'),
            'letras' => NumeroEnLetras::pesos($pago->valor),
            'pendiente' => $pendiente,
        ])->render();

        return Pdf::loadHTML($html)->setPaper([0, 0, 612, 396])
            ->setOption(['isFontSubsettingEnabled' => true, 'isRemoteEnabled' => false])->output();
    }

    public static function nombreRecibo(PagoAdministracion $pago): string
    {
        return 'recibo-administracion-'.$pago->numero_recibo.'.pdf';
    }
}
