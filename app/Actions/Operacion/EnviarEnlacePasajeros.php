<?php

namespace App\Actions\Operacion;

use App\Mail\CorreoListadoPasajeros;
use App\Models\Empresa;
use App\Models\Fuec;
use App\Services\Mensajeria\Mensajero;
use App\Support\Contacto;
use Illuminate\Validation\ValidationException;

/**
 * Envía al cliente del contrato el enlace para que los pasajeros se autorregistren en el
 * listado del FUEC (Turismo / Grupo específico), por correo y/o SMS. Va por la cola.
 *
 * @return list<string> canales por los que se envió (para avisar al operador)
 */
class EnviarEnlacePasajeros
{
    public function __invoke(Fuec $fuec): array
    {
        if (! $fuec->listadoAbierto()) {
            throw ValidationException::withMessages([
                'enlace' => 'El listado de pasajeros no está abierto; no hay enlace que compartir.',
            ]);
        }

        $enlace = $fuec->urlListadoPasajeros();

        if (! $enlace) {
            throw ValidationException::withMessages(['enlace' => 'No se pudo generar el enlace del listado.']);
        }

        $fuec->loadMissing('contrato.cliente');
        $empresa = Empresa::findOrFail($fuec->empresa_id);
        $cliente = $fuec->contrato?->cliente;

        $email = $cliente?->email;
        $celular = $cliente?->telefono ?: $fuec->contrato?->responsable_telefono;
        $nombreEmpresa = $empresa->nombre_comercial ?: $empresa->razon_social;
        $destinatario = $cliente?->nombre ?: ($fuec->contrato?->responsable_nombre ?: 'cliente');

        $canales = [];

        if ($email && Contacto::correoValido($email)) {
            Mensajero::correo($empresa, 'listado_pasajeros', $email, new CorreoListadoPasajeros(
                $nombreEmpresa,
                $destinatario,
                $fuec->numero_formateado,
                $enlace,
                $fuec->cuposDisponibles(),
            ));
            $canales[] = 'correo '.Contacto::ocultarCorreo($email);
        }

        if ($celular && Contacto::celular($celular)) {
            $texto = Contacto::textoSms($nombreEmpresa)
                .": registre los pasajeros del FUEC {$fuec->numero_formateado} aquí: {$enlace}";
            $envio = Mensajero::sms($empresa, 'listado_pasajeros', $celular, $texto);

            if (! in_array($envio->estado, ['omitido', 'fallido'], true)) {
                $canales[] = 'SMS '.Contacto::ocultarCelular($celular);
            }
        }

        if ($canales === []) {
            throw ValidationException::withMessages([
                'enlace' => 'El cliente del contrato no tiene correo ni celular válidos. Comparte el enlace manualmente.',
            ]);
        }

        return $canales;
    }
}
