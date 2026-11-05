<?php

namespace App\Services\Rndc;

use App\Jobs\ReintentarEnvioRndc;
use App\Models\Conductor;
use App\Models\Empresa;
use App\Models\Propietario;
use App\Models\RegistroRndc;
use App\Models\Remolque;
use App\Models\TerceroCarga;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Registro de terceros y vehículos en el RNDC.
 * - Con la conexión activa (usuario del RNDC en Integraciones): envía por el web service.
 * - Sin conexión: la empresa descarga la ficha en PDF, registra en la web del RNDC y anota aquí
 *   el número de radicado (registro manual).
 */
final class Rndc
{
    public static function conectado(Empresa $empresa): bool
    {
        return $empresa->rndc_conectado && filled($empresa->rndc_usuario) && filled($empresa->rndc_clave);
    }

    /**
     * Lo que impide registrar: validaciones del diccionario de datos y que el propietario y el
     * tenedor de un vehículo ya estén registrados (el RNDC lo exige).
     *
     * @return list<string>
     */
    public static function pendientes(RegistrableRndc&Model $registro): array
    {
        $errores = $registro->validarRndc();

        if ($registro instanceof Vehiculo || $registro instanceof Remolque) {
            $propietario = $registro->propietario()->withoutGlobalScopes()->first();
            $tenedor = $registro->tenedor()->first() ?? $propietario;
            foreach (['propietario' => $propietario, 'tenedor' => $tenedor] as $rol => $p) {
                if ($p && ! in_array($p->estadoRndc(), ['registrado', 'desactualizado'], true)) {
                    $errores[] = "Registre primero en el RNDC al {$rol} ({$p->etiquetaRndc()}).";
                }
            }
        }

        return array_values(array_unique($errores));
    }

    public function registrar(RegistrableRndc&Model $registro, ?User $usuario = null, ?ClienteRndc $cliente = null): RegistroRndc
    {
        $empresa = Empresa::findOrFail($registro->empresa_id);
        if (! self::conectado($empresa)) {
            throw ValidationException::withMessages(['rndc' => 'La conexión con el RNDC no está activa (Configuración → Integraciones). Descargue la ficha en PDF, regístrelo en la web del RNDC y anote aquí el número de radicado.']);
        }
        if ($errores = self::pendientes($registro)) {
            throw ValidationException::withMessages(['rndc' => $errores]);
        }

        $variables = $registro->variablesRndc($empresa);
        $respuesta = ($cliente ?? new ClienteRndc($empresa))->enviar(1, $registro->procesoRndc(), $variables);

        return self::guardar($empresa, $registro, $registro->procesoRndc(), $variables, $respuesta, $usuario);
    }

    /**
     * Guarda el envío y, si el RNDC estaba caído, lo deja en contingencia y programa el reintento.
     * Una caída no es lo mismo que un rechazo de datos: la primera se reintenta sola.
     */
    private static function guardar(Empresa $empresa, Model $documento, int $proceso, array $variables, RespuestaRndc $respuesta, ?User $usuario): RegistroRndc
    {
        $contingencia = $respuesta->reintentable();

        $registroRndc = RegistroRndc::query()->create([
            'empresa_id' => $empresa->id,
            'registrable_type' => $documento->getMorphClass(),
            'registrable_id' => $documento->getKey(),
            'proceso' => $proceso,
            'estado' => match (true) {
                $respuesta->ok() => 'registrado', $contingencia => 'contingencia', default => 'error'
            },
            'ingresoid' => $respuesta->ingresoid,
            'ambiente' => $empresa->rndc_ambiente,
            'variables' => $variables,
            'respuesta' => mb_substr($respuesta->crudo, 0, 5000),
            'errores' => $respuesta->errores ?: null,
            'user_id' => $usuario?->id ?? auth()->id(),
            'duracion_ms' => $respuesta->duracionMs,
            'reintentar_a' => $contingencia ? now()->addMinutes(ReintentarEnvioRndc::ESPERAS[0]) : null,
        ]);

        // No se encola aquí: la tarea `ruta:rndc-contingencia` recoge cada diez minutos
        // lo que ya cumplió su hora. Un solo mecanismo, y sobrevive al reinicio del worker.
        return $registroRndc;
    }

    /** Registro hecho en la web del RNDC: se anota el número de radicado. */
    public function marcarManual(RegistrableRndc&Model $registro, string $ingresoid, ?User $usuario = null): RegistroRndc
    {
        $ingresoid = trim($ingresoid);
        if (! preg_match('/^\d{1,30}$/', $ingresoid)) {
            throw ValidationException::withMessages(['ingresoid' => 'El número de radicado del RNDC es solo números.']);
        }
        $empresa = Empresa::findOrFail($registro->empresa_id);

        return RegistroRndc::query()->create([
            'empresa_id' => $empresa->id,
            'registrable_type' => $registro->getMorphClass(),
            'registrable_id' => $registro->getKey(),
            'proceso' => $registro->procesoRndc(),
            'estado' => 'manual',
            'ingresoid' => $ingresoid,
            'ambiente' => 'manual',
            'variables' => $registro->variablesRndc($empresa),
            'user_id' => $usuario?->id ?? auth()->id(),
        ]);
    }

    /**
     * Anula (o libera, en un transbordo) un documento ya radicado en el RNDC.
     *
     * @param  array<string, mixed>  $datos
     */
    public function anular(AnulableRndc&Model $documento, array $datos, ?User $usuario = null, ?ClienteRndc $cliente = null): RegistroRndc
    {
        $empresa = Empresa::findOrFail($documento->empresa_id);

        if (! self::conectado($empresa)) {
            throw ValidationException::withMessages(['rndc' => 'La conexión con el RNDC no está activa (Configuración → Integraciones). Anule en la web del RNDC y anote aquí el radicado.']);
        }

        if ($errores = $documento->pendientesAnulacionRndc($datos)) {
            throw ValidationException::withMessages(['rndc' => $errores]);
        }

        $proceso = $documento->procesoAnulacionRndc();
        $variables = $documento->variablesAnulacionRndc($empresa, $datos);
        $respuesta = ($cliente ?? new ClienteRndc($empresa))->enviar(1, $proceso, $variables);

        return self::guardar($empresa, $documento, $proceso, $variables, $respuesta, $usuario);
    }

    /** XML que se enviaría (sin la clave), para revisar o cargar en la herramienta de pruebas del RNDC. */
    public static function xml(RegistrableRndc&Model $registro): string
    {
        $empresa = Empresa::findOrFail($registro->empresa_id);

        return ClienteRndc::request(filled($empresa->rndc_usuario) ? (string) $empresa->rndc_usuario : 'USUARIO', '********', 1,
            $registro->procesoRndc(), $registro->variablesRndc($empresa));
    }

    /** Clase corta para rutas y permisos. */
    public static function tipos(): array
    {
        return ['tercero' => TerceroCarga::class, 'propietario' => Propietario::class, 'conductor' => Conductor::class,
            'vehiculo' => Vehiculo::class, 'remolque' => Remolque::class];
    }
}
