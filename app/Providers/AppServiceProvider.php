<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Modulos;
use App\Support\Sistema\Latidos;
use App\Support\Sistema\Referencia;
use App\Support\Sistema\UrlApp;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Si APP_URL quedó en localhost (sin configurar en el servidor), los archivos públicos (logos)
        // se enlazan con la dirección por la que entró el usuario, no con «localhost».
        if (! $this->app->runningInConsole() && UrlApp::sinConfigurar()) {
            config(['filesystems.disks.public.url' => request()->getSchemeAndHttpHost().'/storage']);
        }

        // Cola: latido del worker (Estado del sistema) y referencia de error nueva por cada trabajo.
        Queue::looping(fn () => Latidos::marcar(Latidos::WORKER));
        Queue::before(function (JobProcessing $evento): void {
            // Los trabajos síncronos (sync) son parte de la misma petición: conservan su referencia.
            if ($evento->connectionName !== 'sync') {
                Referencia::reiniciar();
            }
        });

        // Páginas públicas de firma: límites por IP, cada uno con su propia clave.
        foreach (['firma' => 60, 'firma-codigo' => 8, 'firma-firmar' => 20, 'verificar-archivo' => 10, 'conductor-ingreso' => 10] as $nombre => $porMinuto) {
            RateLimiter::for($nombre, fn (Request $request) => Limit::perMinute($porMinuto)->by($nombre.'|'.$request->ip()));
        }

        // Fechas siempre como dd/mm/aaaa, sin depender del idioma del navegador.
        DatePicker::configureUsing(fn (DatePicker $campo) => $campo->native(false)->displayFormat('d/m/Y')->firstDayOfWeek(1));

        Carbon::setLocale(config('app.locale'));

        // Detecta errores comunes durante el desarrollo (carga perezosa, atributos inexistentes).
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // El superadministrador de la plataforma tiene todos los permisos.
        // Módulos contratados: los permisos de un módulo que la empresa no tiene se niegan a todos
        // (también al superadministrador dentro de esa empresa). Así desaparece del menú y no abre.
        Gate::before(function (User $user, string $habilidad, array $argumentos = []) {
            if (Modulos::permisoBloqueado($habilidad)) {
                return false;
            }
            // Habilidades de política (viewAny, create…): el recurso sale de la constante RECURSO de la política.
            $politica = $argumentos !== [] && (is_object($argumentos[0]) || (is_string($argumentos[0]) && class_exists($argumentos[0])))
                ? Gate::getPolicyFor($argumentos[0]) : null;
            if ($politica && defined($politica::class.'::RECURSO') && Modulos::permisoBloqueado('ver_'.$politica::RECURSO)) {
                return false;
            }

            return $user->es_superadmin ? true : null;
        });

        // Pie del menú lateral: nombre de la plataforma.
        FilamentView::registerRenderHook(
            PanelsRenderHook::SIDEBAR_FOOTER,
            fn (): string => Blade::render('<div class="ruta-sidebar-footer">Plataforma de transporte · SolucionesApp</div>'),
        );
    }
}
