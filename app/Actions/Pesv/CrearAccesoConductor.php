<?php

namespace App\Actions\Pesv;

use App\Enums\EstadoConductor;
use App\Models\Conductor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Da acceso al portal del conductor (o restablece su clave). El conductor ingresa con su número
 * de documento y una clave temporal que debe cambiar al entrar. La clave se muestra una sola vez.
 *
 * Si la misma persona es conductor en otra empresa y ya tiene usuario, se reutiliza: ve en el
 * portal los servicios de todas sus empresas.
 */
class CrearAccesoConductor
{
    /** @return array{usuario: User, clave: string, nuevo: bool} */
    public function __invoke(Conductor $conductor): array
    {
        if ($conductor->estado === EstadoConductor::Retirado) {
            throw ValidationException::withMessages(['conductor' => 'El conductor está retirado: no puede tener acceso al portal.']);
        }

        $clave = self::claveTemporal();

        return DB::transaction(function () use ($conductor, $clave): array {
            $usuario = $conductor->usuario
                ?? User::query()->whereIn('id', Conductor::query()->withoutGlobalScopes()
                    ->where('documento', $conductor->documento)->whereNotNull('user_id')->select('user_id'))->first();

            $nuevo = ! $usuario;

            if ($nuevo) {
                $usuario = User::create([
                    'name' => $conductor->nombre_completo,
                    'email' => null,
                    'tipo_identificacion_id' => $conductor->tipo_identificacion_id,
                    'documento' => $conductor->documento,
                    'telefono' => $conductor->telefono,
                    'password' => $clave,
                    'debe_cambiar_clave' => true,
                ]);
            } else {
                $usuario->forceFill(['password' => $clave, 'debe_cambiar_clave' => true, 'activo' => true])->save();
            }

            if ($conductor->user_id !== $usuario->id) {
                $conductor->forceFill(['user_id' => $usuario->id])->save();
            }

            activity('Conductor')->performedOn($conductor)->causedBy(auth()->user())
                ->tap(fn ($a) => $a->empresa_id = $conductor->empresa_id)
                ->log($nuevo ? 'Creó el acceso al portal del conductor' : 'Restableció la clave del portal del conductor');

            return ['usuario' => $usuario, 'clave' => $clave, 'nuevo' => $nuevo];
        });
    }

    /** 8 caracteres fáciles de dictar (sin 0/O, 1/l/I). */
    public static function claveTemporal(): string
    {
        $letras = 'abcdefghjkmnpqrstuvwxyz';
        $numeros = '23456789';
        $clave = '';
        for ($i = 0; $i < 4; $i++) {
            $clave .= $letras[random_int(0, strlen($letras) - 1)];
        }
        for ($i = 0; $i < 4; $i++) {
            $clave .= $numeros[random_int(0, strlen($numeros) - 1)];
        }

        return $clave;
    }
}
