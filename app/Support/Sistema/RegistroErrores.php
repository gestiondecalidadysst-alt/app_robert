<?php

namespace App\Support\Sistema;

use App\Models\ErrorSistema;
use App\Models\OcurrenciaError;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Registra los errores inesperados (los que Laravel reporta: no incluye validaciones, 404, 403 ni
 * sesión vencida) agrupados por huella, y avisa a los superadministradores en la campana —y por
 * correo si ALERTAS_CORREO está configurado— la primera vez y, si se repite, máximo una vez por hora.
 *
 * Nunca lanza: si la base de datos no responde, el error sigue quedando en el log.
 */
final class RegistroErrores
{
    public const MINUTOS_ENTRE_ALERTAS = 60;

    /** Ocurrencias que se conservan por error (las más recientes); evita que la tabla crezca sin fin. */
    public const OCURRENCIAS_POR_ERROR = 50;

    private static bool $registrando = false;

    public static function registrar(Throwable $e): ?ErrorSistema
    {
        if (self::$registrando) {
            return null;
        }
        self::$registrando = true;

        try {
            $archivo = self::rutaRelativa($e->getFile());
            $mensaje = Str::limit(trim($e->getMessage()) ?: '(sin mensaje)', 2000);
            // La huella ignora números para agrupar "id 15" e "id 16" como el mismo error.
            $huella = hash('sha256', get_class($e).'|'.$archivo.'|'.$e->getLine().'|'.preg_replace('/\d+/', '#', Str::limit($mensaje, 300)));
            $ahora = now();
            $peticion = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

            $error = ErrorSistema::query()->firstOrNew(['huella' => $huella]);
            $nuevo = ! $error->exists;
            $error->fill([
                'referencia' => Referencia::actual(),
                'clase' => get_class($e),
                'mensaje' => $mensaje,
                'archivo' => $archivo,
                'linea' => $e->getLine(),
                'metodo' => $peticion?->method() ?? 'CLI',
                'url' => $peticion ? Str::limit($peticion->fullUrl(), 490) : Str::limit(implode(' ', $_SERVER['argv'] ?? []), 490),
                'user_id' => auth()->id(),
                'empresa_id' => self::empresaActual(),
                'traza' => Str::limit($e->getTraceAsString(), 8000),
                'ultima_vez' => $ahora,
                'primera_vez' => $error->primera_vez ?? $ahora,
                'ocurrencias' => $nuevo ? 1 : $error->ocurrencias + 1,
                // Si vuelve a pasar, se reabre.
                'resuelto_at' => null,
            ]);

            $alertar = $nuevo || ! $error->alertado_at || $error->alertado_at->lt($ahora->copy()->subMinutes(self::MINUTOS_ENTRE_ALERTAS));
            if ($alertar) {
                $error->alertado_at = $ahora;
            }
            $error->save();

            self::guardarOcurrencia($error);

            if ($alertar) {
                self::alertar($error, $nuevo);
            }

            return $error;
        } catch (Throwable) {
            return null;
        } finally {
            self::$registrando = false;
        }
    }

    /**
     * Guarda el rastro de esta ocurrencia (para diagnosticar y para poder buscar por cualquier
     * referencia que reporte un usuario) y poda las más antiguas.
     */
    private static function guardarOcurrencia(ErrorSistema $error): void
    {
        OcurrenciaError::query()->create([
            'error_sistema_id' => $error->id,
            'referencia' => $error->referencia,
            'url' => $error->url,
            'metodo' => $error->metodo,
            'user_id' => $error->user_id,
            'empresa_id' => $error->empresa_id,
            'ocurrio_at' => $error->ultima_vez,
        ]);

        $sobran = $error->ocurrencias()->count() - self::OCURRENCIAS_POR_ERROR;

        if ($sobran > 0) {
            $viejas = OcurrenciaError::query()->where('error_sistema_id', $error->id)
                ->orderBy('ocurrio_at')->limit($sobran)->pluck('id');

            OcurrenciaError::query()->whereIn('id', $viejas)->delete();
        }
    }

    private static function alertar(ErrorSistema $error, bool $nuevo): void
    {
        $titulo = ($nuevo ? 'Error nuevo' : "Error repetido ({$error->ocurrencias} veces)")." · {$error->referencia}";
        $cuerpo = "{$error->clase_corta}: ".Str::limit($error->mensaje, 180)." — {$error->archivo}:{$error->linea}";

        try {
            $notificacion = Notification::make()->danger()->title($titulo)->body($cuerpo)
                ->actions([Action::make('ver')->label('Ver en Estado del sistema')->url(url('/admin/estado-sistema'))]);
            User::query()->withoutGlobalScopes()->where('es_superadmin', true)->where('activo', true)->get()
                ->each(fn (User $u) => $notificacion->sendToDatabase($u));
        } catch (Throwable) {
        }

        if ($correo = config('ruta.alertas_correo')) {
            try {
                Mail::raw("{$titulo}\n\n{$cuerpo}\n\nURL: {$error->url}\nUsuario: ".($error->user_id ?? '—')."\nEmpresa: ".($error->empresa_id ?? '—')
                    ."\n\nDetalle en ".url('/admin/estado-sistema'), fn ($m) => $m->to($correo)->subject('[Ruta] '.$titulo));
            } catch (Throwable) {
            }
        }

        AvisoPlataforma::enviar(AvisoPlataforma::ERROR, $titulo, [
            'referencia' => $error->referencia,
            'clase' => $error->clase_corta,
            'mensaje' => Str::limit($error->mensaje, 300),
            'donde' => "{$error->archivo}:{$error->linea}",
            'url' => $error->url,
            'metodo' => $error->metodo,
            'ocurrencias' => $error->ocurrencias,
            'nuevo' => $nuevo,
            'empresa_id' => $error->empresa_id,
            'user_id' => $error->user_id,
            'detalle' => url('/admin/estado-sistema'),
        ]);
    }

    private static function empresaActual(): ?int
    {
        try {
            return Filament::getTenant()?->getKey();
        } catch (Throwable) {
            return null;
        }
    }

    private static function rutaRelativa(string $archivo): string
    {
        return Str::after($archivo, base_path().DIRECTORY_SEPARATOR);
    }
}
