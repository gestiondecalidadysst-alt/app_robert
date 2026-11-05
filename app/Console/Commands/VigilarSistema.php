<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Sistema\EstadoColas;
use App\Support\Sistema\Latidos;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * Cada 5 minutos: si el worker de la cola no da señales, hay trabajos atascados o aparecieron
 * trabajos fallidos nuevos, avisa a los superadministradores (campana y ALERTAS_CORREO), máximo una
 * vez por hora por cada tipo de problema.
 */
class VigilarSistema extends Command
{
    protected $signature = 'ruta:vigilar';

    protected $description = 'Avisa si la cola está caída o atascada, o si hay trabajos fallidos nuevos';

    public function handle(): int
    {
        $problemas = [];

        if (! Latidos::vivo(Latidos::WORKER)) {
            $ultimo = Latidos::ultimo(Latidos::WORKER);
            $problemas['worker'] = 'El worker de la cola no responde'.($ultimo ? ' desde el '.$ultimo->format('d/m h:i a') : '').'. Revise el servicio worker en Easypanel.';
        }
        if ($atascados = EstadoColas::atascados()) {
            $problemas['atascados'] = "{$atascados} trabajo(s) llevan más de ".Latidos::MINUTOS_CAIDO.' minutos esperando en la cola.';
        }

        $fallidos = EstadoColas::fallidos();
        $antes = (int) Cache::get('vigilante.fallidos', 0);
        Cache::forever('vigilante.fallidos', $fallidos);
        if ($fallidos > $antes) {
            $problemas['fallidos'] = ($fallidos - $antes).' trabajo(s) fallaron después de todos sus reintentos.';
        }

        foreach ($problemas as $tipo => $mensaje) {
            if (! Cache::add("vigilante.aviso.{$tipo}", true, now()->addHour())) {
                continue;
            }
            $this->warn($mensaje);
            $notificacion = Notification::make()->danger()->title('Estado del sistema')->body($mensaje)
                ->actions([Action::make('ver')->label('Ver')->url(url('/admin/estado-sistema'))]);
            User::query()->withoutGlobalScopes()->where('es_superadmin', true)->where('activo', true)->get()
                ->each(fn (User $u) => $notificacion->sendToDatabase($u));
            if ($correo = config('ruta.alertas_correo')) {
                rescue(fn () => Mail::raw($mensaje."\n\n".url('/admin/estado-sistema'), fn ($m) => $m->to($correo)->subject('[Ruta] Estado del sistema')), report: false);
            }
        }

        if (! $problemas) {
            $this->info('Todo en orden.');
        }

        return self::SUCCESS;
    }
}
