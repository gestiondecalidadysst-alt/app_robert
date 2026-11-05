<?php

namespace App\Jobs;

use App\Models\Empresa;
use App\Models\RegistroRndc;
use App\Services\Rndc\ClienteRndc;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Contingencia del RNDC: reenvía lo que quedó en espera porque el servicio del
 * Ministerio no respondió.
 *
 * Solo reintenta caídas del servicio. Un rechazo de datos (TER020, MAN010…) no se
 * reintenta nunca: hay que corregir el documento, y repetirlo sería ruido.
 */
class ReintentarEnvioRndc implements ShouldQueue
{
    use Queueable;

    /** Espera entre intentos, creciente: 5 min, 15, 30, 1 h, 2 h, 4 h. */
    public const ESPERAS = [5, 15, 30, 60, 120, 240];

    public int $tries = 1;

    public function __construct(public readonly int $registroId) {}

    public function handle(): void
    {
        $registro = RegistroRndc::query()->withoutGlobalScopes()->find($this->registroId);

        if (! $registro || $registro->estado !== 'contingencia') {
            return; // Ya lo resolvieron a mano o se radicó por otra vía.
        }

        $empresa = Empresa::find($registro->empresa_id);

        if (! $empresa || ! $empresa->rndc_conectado) {
            return;
        }

        $respuesta = (new ClienteRndc($empresa))->enviar(1, $registro->proceso, $registro->variables ?? []);
        $intentos = (int) $registro->intentos + 1;

        if ($respuesta->ok()) {
            $registro->update([
                'estado' => 'registrado', 'ingresoid' => $respuesta->ingresoid, 'errores' => null,
                'respuesta' => mb_substr($respuesta->crudo, 0, 5000),
                'intentos' => $intentos, 'reintentar_a' => null, 'duracion_ms' => $respuesta->duracionMs,
            ]);

            return;
        }

        // El RNDC contestó rechazando: no se reintenta, hay que corregir.
        if (! $respuesta->reintentable()) {
            $registro->update([
                'estado' => 'error', 'errores' => $respuesta->errores,
                'respuesta' => mb_substr($respuesta->crudo, 0, 5000),
                'intentos' => $intentos, 'reintentar_a' => null,
            ]);

            return;
        }

        // Sigue caído: se vuelve a programar, o se abandona tras agotar las esperas.
        if ($intentos >= count(self::ESPERAS)) {
            $registro->update([
                'estado' => 'error', 'intentos' => $intentos, 'reintentar_a' => null,
                'errores' => [['codigo' => null, 'mensaje' => 'El RNDC siguió sin responder tras '.$intentos.' intentos. Radique en la web del RNDC y anote aquí el número.']],
            ]);

            Log::warning('RNDC en contingencia agotó los reintentos', ['registro' => $registro->id, 'empresa' => $empresa->id]);

            return;
        }

        // Solo se apunta la próxima hora. Quien vuelve a encolar es la tarea programada
        // `ruta:rndc-contingencia`: si el job se reencolara a sí mismo, con cola síncrona
        // se gastarían los seis intentos de una vez, sin respetar las esperas.
        $registro->update([
            'intentos' => $intentos,
            'reintentar_a' => now()->addMinutes(self::ESPERAS[$intentos]),
            'errores' => $respuesta->errores,
        ]);
    }
}
