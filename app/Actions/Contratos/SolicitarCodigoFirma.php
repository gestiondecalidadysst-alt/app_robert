<?php

namespace App\Actions\Contratos;

use App\Mail\CorreoFirmaContrato;
use App\Models\CodigoFirma;
use App\Models\DocumentoContrato;
use App\Models\Empresa;
use App\Services\Mensajeria\Mensajero;
use App\Support\Contacto;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Genera y envía el código de verificación de un solo uso para firmar.
 * Principal: SMS al celular registrado. Alternativa: correo registrado.
 * Máximo 3 códigos cada 15 minutos por documento; el código vence a los 10 minutos.
 */
class SolicitarCodigoFirma
{
    /**
     * @return array{canal: string, destino: string}
     */
    public function __invoke(DocumentoContrato $documento, ?string $canalPreferido = null, ?string $ip = null, ?string $userAgent = null): array
    {
        if (! $documento->admiteFirma()) {
            throw ValidationException::withMessages(['codigo' => 'Este enlace ya no permite firmar.']);
        }

        $recientes = $documento->codigos()->where('created_at', '>=', now()->subMinutes(CodigoFirma::MINUTOS_VENTANA))->count();
        if ($recientes >= CodigoFirma::MAX_POR_VENTANA) {
            throw ValidationException::withMessages([
                'codigo' => 'Ya enviamos ' . CodigoFirma::MAX_POR_VENTANA . ' códigos. Espere unos minutos e intente de nuevo.',
            ]);
        }

        $empresa = Empresa::findOrFail($documento->empresa_id);
        $canales = self::canales($documento, $empresa);

        if ($canales === []) {
            throw ValidationException::withMessages(['codigo' => 'No hay un celular ni un correo para enviar el código. Comuníquese con la empresa.']);
        }

        $canal = in_array($canalPreferido, $canales, true) ? $canalPreferido : $canales[0];
        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $destino = $canal === 'sms' ? Contacto::ocultarCelular($documento->firmante_celular) : Contacto::ocultarCorreo($documento->firmante_email);

        // Un solo código válido a la vez.
        $documento->codigos()->whereNull('usado_at')->where('expira_at', '>', now())->update(['expira_at' => now()]);

        $registro = $documento->codigos()->create([
            'canal' => $canal,
            'destino' => $destino,
            'codigo_hash' => CodigoFirma::huella($codigo),
            'expira_at' => now()->addMinutes(CodigoFirma::MINUTOS_VIGENCIA),
            'ip' => $ip,
        ]);

        $numero = $documento->datos['valores']['contrato.numero'] ?? '';
        $nombreEmpresa = $documento->datos['empresa']['nombre_comercial'] ?: $documento->datos['empresa']['razon_social'];

        $envio = $canal === 'sms'
            ? Mensajero::sms($empresa, 'codigo_firma', $documento->firmante_celular,
                Str::limit(Contacto::textoSms($nombreEmpresa), 24, '') . ": su codigo para firmar el contrato No {$numero} es {$codigo}. Vence en 10 min. No lo comparta.",
                $documento, ahora: true)
            : Mensajero::correo($empresa, 'codigo_firma', $documento->firmante_email,
                new CorreoFirmaContrato(CorreoFirmaContrato::CODIGO, $nombreEmpresa, $numero, (string) $documento->firmante_nombre, codigo: $codigo),
                $documento, ahora: true);

        if ($envio->estado !== 'enviado') {
            $registro->update(['expira_at' => now()]);
            $otro = array_values(array_diff($canales, [$canal]))[0] ?? null;

            throw ValidationException::withMessages([
                'codigo' => 'No pudimos enviar el código por ' . ($canal === 'sms' ? 'SMS' : 'correo') . '.'
                    . ($otro ? ' Intente por ' . ($otro === 'sms' ? 'SMS' : 'correo') . '.' : ' Intente de nuevo en un momento.'),
            ]);
        }

        $documento->registrar('codigo_enviado', ['canal' => $canal === 'sms' ? 'SMS' : 'correo', 'destino' => $destino], $ip, $userAgent);

        return ['canal' => $canal, 'destino' => $destino];
    }

    /** Canales disponibles para el código, en orden de preferencia. */
    public static function canales(DocumentoContrato $documento, Empresa $empresa): array
    {
        return array_values(array_filter([
            $documento->firmante_celular && Mensajero::motivoSinSms($empresa) === null ? 'sms' : null,
            Contacto::correoValido($documento->firmante_email) ? 'correo' : null,
        ]));
    }
}
