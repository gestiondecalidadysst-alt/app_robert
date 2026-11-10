<?php

namespace App\Support\Sistema;

use App\Models\EnvioMensaje;
use App\Models\WebhookEntrega;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Throwable;

/**
 * Chequeos de salud de la aplicación y de los servicios de los que depende. Los usa la pantalla
 * «Estado del sistema», el endpoint /salud (para monitores externos) y el vigilante, que avisa
 * cuando algo se cae.
 *
 * Cada chequeo devuelve: clave, nombre, ok (bool) y detalle legible. Ninguno lanza excepciones:
 * si algo falla, eso mismo es el resultado del chequeo.
 */
final class Salud
{
    /** Fallos de envío en la última hora a partir de los cuales se considera que el proveedor falla. */
    private const TOPE_FALLOS_PROVEEDOR = 5;

    /** Horas sin respaldo a partir de las cuales se considera un problema. */
    private const HORAS_SIN_RESPALDO = 48;

    /** @return list<array{clave:string, nombre:string, ok:bool, detalle:string}> */
    public static function chequeos(): array
    {
        return array_values(array_filter([
            self::baseDatos(),
            self::cache(),
            self::almacenamiento(),
            self::worker(),
            self::programadas(),
            self::cola(),
            self::trabajosFallidos(),
            self::proveedorCorreo(),
            self::proveedor('sms', 'SMS (Onurix)'),
            self::webhooks(),
            self::respaldos(),
        ]));
    }

    /** ¿Está todo sano? */
    public static function sano(?array $chequeos = null): bool
    {
        foreach ($chequeos ?? self::chequeos() as $c) {
            if (! $c['ok']) {
                return false;
            }
        }

        return true;
    }

    /** @return list<array{clave:string, nombre:string, ok:bool, detalle:string}> */
    public static function fallando(): array
    {
        return array_values(array_filter(self::chequeos(), fn (array $c) => ! $c['ok']));
    }

    private static function resultado(string $clave, string $nombre, bool $ok, string $detalle): array
    {
        return ['clave' => $clave, 'nombre' => $nombre, 'ok' => $ok, 'detalle' => $detalle];
    }

    private static function baseDatos(): array
    {
        try {
            DB::select('select 1');

            return self::resultado('base_datos', 'Base de datos', true, 'Responde correctamente.');
        } catch (Throwable $e) {
            return self::resultado('base_datos', 'Base de datos', false, 'No responde: '.Str::limit($e->getMessage(), 120));
        }
    }

    private static function cache(): array
    {
        try {
            $clave = 'salud.prueba';
            $valor = (string) now()->timestamp;
            Cache::put($clave, $valor, 30);
            $leido = Cache::get($clave);
            Cache::forget($clave);

            return $leido === $valor
                ? self::resultado('cache', 'Caché / Redis', true, 'Escribe y lee correctamente.')
                : self::resultado('cache', 'Caché / Redis', false, 'No devuelve lo que se guardó.');
        } catch (Throwable $e) {
            return self::resultado('cache', 'Caché / Redis', false, 'Falla: '.Str::limit($e->getMessage(), 120));
        }
    }

    private static function almacenamiento(): array
    {
        try {
            $ruta = 'salud/prueba-'.Str::random(8).'.txt';
            Storage::disk('local')->put($ruta, 'ok');
            $ok = Storage::disk('local')->get($ruta) === 'ok';
            Storage::disk('local')->delete($ruta);

            return $ok
                ? self::resultado('almacenamiento', 'Almacenamiento de archivos', true, 'Se puede escribir y leer.')
                : self::resultado('almacenamiento', 'Almacenamiento de archivos', false, 'No se pudo leer lo que se escribió.');
        } catch (Throwable $e) {
            return self::resultado('almacenamiento', 'Almacenamiento de archivos', false, 'Falla: '.Str::limit($e->getMessage(), 120));
        }
    }

    private static function worker(): array
    {
        $vivo = Latidos::vivo(Latidos::WORKER);
        $ultimo = Latidos::ultimo(Latidos::WORKER);

        return self::resultado('worker', 'Worker de la cola', $vivo, $vivo
            ? 'Activo (último latido '.$ultimo?->diffForHumans().').'
            : 'Sin señales'.($ultimo ? ' desde '.$ultimo->diffForHumans() : '').'. Revise el servicio worker.');
    }

    private static function programadas(): array
    {
        $vivo = Latidos::vivo(Latidos::PROGRAMADAS);
        $ultimo = Latidos::ultimo(Latidos::PROGRAMADAS);

        return self::resultado('programadas', 'Tareas programadas', $vivo, $vivo
            ? 'Activas (último latido '.$ultimo?->diffForHumans().').'
            : 'Sin señales'.($ultimo ? ' desde '.$ultimo->diffForHumans() : '').'. Revise el servicio scheduler.');
    }

    private static function cola(): array
    {
        try {
            $atascados = EstadoColas::atascados();

            return self::resultado('cola', 'Cola de trabajos', $atascados === 0, $atascados === 0
                ? 'Sin trabajos atascados.'
                : "{$atascados} trabajo(s) llevan más de ".Latidos::MINUTOS_CAIDO.' minutos esperando.');
        } catch (Throwable $e) {
            return self::resultado('cola', 'Cola de trabajos', false, 'No se pudo consultar: '.Str::limit($e->getMessage(), 120));
        }
    }

    private static function trabajosFallidos(): array
    {
        try {
            $fallidos = EstadoColas::fallidos();

            return self::resultado('trabajos_fallidos', 'Trabajos fallidos', $fallidos === 0, $fallidos === 0
                ? 'Ninguno pendiente de revisar.'
                : "{$fallidos} trabajo(s) fallaron tras todos sus reintentos.");
        } catch (Throwable $e) {
            return self::resultado('trabajos_fallidos', 'Trabajos fallidos', false, 'No se pudo consultar: '.Str::limit($e->getMessage(), 120));
        }
    }

    /** Salud de un proveedor de envíos: se mide por los fallos recientes, no por llamarlo. */
    /** Segundos que puede tardar el saludo SMTP antes de darlo por fallido. */
    private const SEGUNDOS_SMTP = 5;

    /**
     * Correo: a los envíos fallidos registrados se suma una comprobación real de las credenciales.
     * Sin ella, una clave SMTP inválida que nadie ha ejercido todavía aparecería como sana —y eso
     * es peor que no tener chequeo, porque tranquiliza en falso.
     */
    private static function proveedorCorreo(): array
    {
        $envios = self::proveedor('correo', 'Correo (Brevo)');
        $credenciales = self::credencialesCorreo();

        if ($credenciales === null) {
            return $envios;
        }

        // El detalle dice siempre si las credenciales se comprobaron: si no lo dijera, un «ok» no
        // distinguiría entre «autenticó contra el proveedor» y «nadie ha intentado enviar nada».
        return $credenciales['ok']
            ? self::resultado('proveedor_correo', 'Correo (Brevo)', $envios['ok'], 'Credenciales válidas. '.$envios['detalle'])
            : self::resultado('proveedor_correo', 'Correo (Brevo)', false, $credenciales['detalle']);
    }

    /**
     * Abre la conexión SMTP y autentica, sin enviar nada. Se cachea para no saludar al proveedor en
     * cada consulta a /salud: el resultado sano dura 15 minutos y el fallido 2, para que un arreglo
     * se refleje pronto. Devuelve null cuando no hay nada que comprobar (correo por log o array).
     *
     * @return array{ok:bool, detalle:string}|null
     */
    private static function credencialesCorreo(): ?array
    {
        if (! in_array(config('mail.default'), ['smtp', 'ses', 'postmark', 'resend'], true)) {
            return null;
        }

        if ($guardado = Cache::get('salud.smtp')) {
            return $guardado;
        }

        $resultado = rescue(function (): array {
            $transporte = Mail::mailer()->getSymfonyTransport();

            if (! $transporte instanceof SmtpTransport) {
                return ['ok' => true, 'detalle' => ''];
            }

            $flujo = $transporte->getStream();

            if ($flujo instanceof SocketStream) {
                $flujo->setTimeout(self::SEGUNDOS_SMTP);
            }

            $transporte->start();
            $transporte->stop();

            return ['ok' => true, 'detalle' => ''];
        }, fn (Throwable $e) => [
            'ok' => false,
            'detalle' => 'El proveedor rechaza la conexión o las credenciales: '.Str::limit($e->getMessage(), 200),
        ], report: false);

        Cache::put('salud.smtp', $resultado, now()->addMinutes($resultado['ok'] ? 15 : 2));

        return $resultado;
    }

    private static function proveedor(string $canal, string $nombre): array
    {
        try {
            $fallidos = EnvioMensaje::query()->withoutGlobalScopes()
                ->where('canal', $canal)->where('estado', 'fallido')
                ->where('created_at', '>=', now()->subHour())->count();

            $ok = $fallidos < self::TOPE_FALLOS_PROVEEDOR;

            return self::resultado("proveedor_{$canal}", $nombre, $ok, $ok
                ? ($fallidos === 0 ? 'Sin fallos en la última hora.' : "{$fallidos} fallo(s) en la última hora.")
                : "{$fallidos} envíos fallaron en la última hora: el proveedor puede estar caído o sin saldo.");
        } catch (Throwable $e) {
            return self::resultado("proveedor_{$canal}", $nombre, false, 'No se pudo consultar: '.Str::limit($e->getMessage(), 120));
        }
    }

    private static function webhooks(): array
    {
        try {
            $pendientes = WebhookEntrega::query()->withoutGlobalScopes()->where('estado', 'pendiente')->count();

            return self::resultado('webhooks', 'Webhooks', $pendientes < 50, $pendientes < 50
                ? "{$pendientes} pendiente(s) de entrega."
                : "{$pendientes} webhooks pendientes: el destino puede no estar respondiendo.");
        } catch (Throwable $e) {
            return self::resultado('webhooks', 'Webhooks', false, 'No se pudo consultar: '.Str::limit($e->getMessage(), 120));
        }
    }

    /**
     * Último respaldo en el destino remoto. Consulta el almacenamiento, así que se guarda en caché
     * una hora para no castigar al endpoint de salud.
     */
    private static function respaldos(): ?array
    {
        $disco = config('backup.backup.destination.disks.0');

        if (! $disco || blank(config("filesystems.disks.{$disco}.bucket"))) {
            return null; // Respaldos aún no configurados: no se reporta como fallo.
        }

        try {
            $ultimo = Cache::remember('salud.ultimo_respaldo', 3600, function () use ($disco): ?int {
                $carpeta = (string) config('backup.backup.name');
                $archivos = Storage::disk($disco)->files($carpeta);

                $tiempos = array_map(fn ($a) => Storage::disk($disco)->lastModified($a), $archivos);

                return $tiempos ? max($tiempos) : null;
            });

            if (! $ultimo) {
                return self::resultado('respaldos', 'Respaldos', false, 'No hay ningún respaldo en el destino remoto.');
            }

            $horas = now()->diffInHours(now()->setTimestamp($ultimo), absolute: true);

            return self::resultado('respaldos', 'Respaldos', $horas <= self::HORAS_SIN_RESPALDO,
                'Último respaldo hace '.round($horas).' h.');
        } catch (Throwable $e) {
            return self::resultado('respaldos', 'Respaldos', false, 'No se pudo consultar el destino: '.Str::limit($e->getMessage(), 120));
        }
    }
}
