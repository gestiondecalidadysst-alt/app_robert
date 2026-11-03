<?php

namespace App\Support\Pdf;

use App\Models\Contrato;
use App\Models\Empresa;
use App\Models\PagoContrato;
use App\Support\Contratos\VariablesContrato;
use App\Support\NumeroEnLetras;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Comprobantes de pago de los contratos (para entregar al cliente):
 * - recibo(): recibo de caja de un pago (abono, abono final o pago total), media carta.
 * - estadoCuenta(): relación de todos los pagos del contrato con el saldo; si el saldo es cero
 *   es la constancia de pago total (paz y salvo).
 */
final class PdfRecibo
{
    public static function recibo(PagoContrato $pago): string
    {
        $pago->loadMissing(['contrato.cliente', 'usuario', 'anuladoPor']);
        $contrato = $pago->contrato;
        $empresa = Empresa::query()->with('municipio')->findOrFail($pago->empresa_id);

        $html = view('pdf.recibo-pago', [
            'pago' => $pago,
            'contrato' => $contrato,
            'v' => VariablesContrato::valores($contrato, $empresa, $pago->created_at),
            'empresa' => $empresa,
            'logo' => Imagenes::deDisco($empresa->logo_path, 'public'),
            'qr' => Imagenes::qr($pago->urlVerificacion(), 3),
            'letras' => NumeroEnLetras::pesos($pago->valor),
            'abonadoAntes' => max(0, round((float) $contrato->valor - (float) $pago->saldo_anterior, 2)),
        ])->render();

        // Media carta horizontal (21,6 × 14 cm), el tamaño usual de un recibo de caja.
        return Pdf::loadHTML($html)->setPaper([0, 0, 612, 396])
            ->setOption(['isFontSubsettingEnabled' => true, 'isRemoteEnabled' => false])->output();
    }

    public static function estadoCuenta(Contrato $contrato): string
    {
        $contrato->loadMissing(['cliente', 'pagos' => fn ($q) => $q->with('usuario')->orderBy('fecha')->orderBy('id')]);
        $empresa = Empresa::query()->with('municipio')->findOrFail($contrato->empresa_id);
        $vigentes = $contrato->pagos->whereNull('anulado_at');
        $pagado = round((float) $vigentes->sum('valor'), 2);
        $saldo = max(0, round((float) $contrato->valor - $pagado, 2));

        $html = view('pdf.estado-cuenta', [
            'contrato' => $contrato,
            'pagos' => $contrato->pagos,
            'pagado' => $pagado,
            'saldo' => $saldo,
            'pazYSalvo' => $saldo <= 0 && (float) $contrato->valor > 0 && ! $contrato->anulado(),
            'ultimoPago' => $vigentes->last(),
            'v' => VariablesContrato::valores($contrato, $empresa),
            'empresa' => $empresa,
            'logo' => Imagenes::deDisco($empresa->logo_path, 'public'),
            'firma' => Imagenes::deDisco($empresa->firma_representante_path, 'local'),
            'letras' => NumeroEnLetras::pesos($pagado),
        ])->render();

        return Pdf::loadHTML($html)->setPaper('letter')
            ->setOption(['isFontSubsettingEnabled' => true, 'isRemoteEnabled' => false])->output();
    }

    public static function nombreRecibo(PagoContrato $pago): string
    {
        return 'recibo-' . $pago->numero_recibo . '.pdf';
    }

    public static function nombreEstadoCuenta(Contrato $contrato): string
    {
        return ($contrato->pagado() ? 'paz-y-salvo-contrato-' : 'estado-de-cuenta-contrato-') . $contrato->numero_formateado . '.pdf';
    }
}
