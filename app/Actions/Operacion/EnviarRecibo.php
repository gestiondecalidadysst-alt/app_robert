<?php

namespace App\Actions\Operacion;

use App\Mail\CorreoReciboPago;
use App\Models\Contrato;
use App\Models\Empresa;
use App\Models\PagoContrato;
use App\Services\Mensajeria\Mensajero;
use App\Support\Contacto;
use App\Support\Pdf\PdfRecibo;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Envía al cliente su comprobante:
 * - recibo(): el recibo de caja de un pago, por correo (PDF adjunto) y por SMS (enlace para verlo y descargarlo).
 * - estadoCuenta(): el estado de cuenta o paz y salvo del contrato, por correo (PDF adjunto).
 * Cada envío queda en el historial de mensajes.
 */
class EnviarRecibo
{
    /** @return list<string> canales usados ("correo c***@…", "SMS 300***12") */
    public function recibo(PagoContrato $pago, ?string $email, ?string $celular): array
    {
        $pago->loadMissing('contrato.cliente');
        $empresa = Empresa::findOrFail($pago->empresa_id);
        [$email, $celular] = $this->validar($email, $celular, conSms: true);
        $canales = [];
        $estado = $pago->tipo() === PagoContrato::ABONO
            ? 'Saldo pendiente: $ '.number_format((float) $pago->saldo_despues, 0, ',', '.').'.'
            : 'El contrato quedó pagado en su totalidad.';

        if ($email) {
            Mensajero::correo($empresa, 'recibo_pago', $email, new CorreoReciboPago(
                CorreoReciboPago::RECIBO,
                $this->nombreEmpresa($empresa),
                (string) $pago->contrato->cliente?->nombre,
                $pago->contrato->numero_formateado,
                "Recibo de caja No. {$pago->numero_recibo}",
                "Recibimos su {$this->minuscula($pago->tipoEtiqueta())} por $ ".number_format((float) $pago->valor, 0, ',', '.')
                    ." al contrato No. {$pago->contrato->numero_formateado}. {$estado}",
                base64_encode(PdfRecibo::recibo($pago)),
                PdfRecibo::nombreRecibo($pago),
                $pago->urlVerificacion(),
            ), ahora: true);
            $canales[] = 'correo '.Contacto::ocultarCorreo($email);
        }

        if ($celular) {
            $texto = Str::limit(Contacto::textoSms($this->nombreEmpresa($empresa)), 24, '')
                .": Recibo {$pago->numero_recibo} por $".number_format((float) $pago->valor, 0, ',', '.')
                .' contrato '.$pago->contrato->numero_formateado.': '.$pago->urlVerificacion();
            $envio = Mensajero::sms($empresa, 'recibo_pago', $celular, $texto, ahora: true);
            if (in_array($envio->estado, ['omitido', 'fallido'], true)) {
                if (! $email) {
                    throw ValidationException::withMessages(['celular' => 'SMS no enviado: '.$envio->error]);
                }
            } else {
                $canales[] = 'SMS '.Contacto::ocultarCelular($celular);
            }
        }

        return $canales;
    }

    /** @return list<string> */
    public function estadoCuenta(Contrato $contrato, ?string $email): array
    {
        $contrato->loadMissing('cliente');
        $empresa = Empresa::findOrFail($contrato->empresa_id);
        [$email] = $this->validar($email, null, conSms: false);
        $pagado = $contrato->pagado();

        Mensajero::correo($empresa, 'estado_cuenta', $email, new CorreoReciboPago(
            CorreoReciboPago::ESTADO_CUENTA,
            $this->nombreEmpresa($empresa),
            (string) $contrato->cliente?->nombre,
            $contrato->numero_formateado,
            $pagado ? 'Paz y salvo' : 'Estado de cuenta',
            $pagado
                ? "El contrato No. {$contrato->numero_formateado} está pagado en su totalidad. Le enviamos la constancia de paz y salvo."
                : "Le enviamos el estado de cuenta del contrato No. {$contrato->numero_formateado}. Saldo pendiente: $ ".number_format($contrato->saldo(), 0, ',', '.').'.',
            base64_encode(PdfRecibo::estadoCuenta($contrato)),
            PdfRecibo::nombreEstadoCuenta($contrato),
        ), ahora: true);

        return ['correo '.Contacto::ocultarCorreo($email)];
    }

    /** Datos de contacto sugeridos: los del cliente o, si no, los del responsable del contrato. */
    public static function contacto(Contrato $contrato): array
    {
        $contrato->loadMissing('cliente');

        return [
            'email' => $contrato->cliente?->email,
            'celular' => Contacto::celular($contrato->cliente?->telefono) ?? Contacto::celular($contrato->responsable_telefono),
        ];
    }

    private function validar(?string $email, ?string $celular, bool $conSms): array
    {
        $email = filled($email) ? trim($email) : null;
        $celularValido = filled($celular) ? Contacto::celular($celular) : null;
        $errores = [];

        if ($email !== null && ! Contacto::correoValido($email)) {
            $errores['email'] = 'El correo no es válido.';
        }
        if (filled($celular) && ! $celularValido) {
            $errores['celular'] = 'El celular debe tener 10 dígitos y empezar por 3.';
        }
        if (! $email && ! $celularValido && ! $errores) {
            $errores['email'] = $conSms ? 'Escriba un correo o un celular.' : 'Escriba el correo del cliente.';
        }
        if ($errores) {
            throw ValidationException::withMessages($errores);
        }

        return [$email, $celularValido];
    }

    private function nombreEmpresa(Empresa $empresa): string
    {
        return $empresa->nombre_comercial ?: $empresa->razon_social;
    }

    private function minuscula(string $texto): string
    {
        return mb_strtolower($texto);
    }
}
