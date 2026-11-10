<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Sistema\AvisoPlataforma;
use App\Support\Sistema\EstadoColas;
use App\Support\Sistema\Salud;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * Cada 5 minutos corre los chequeos de salud (base de datos, caché, almacenamiento, worker,
 * tareas programadas, cola, proveedores de correo y SMS, webhooks y respaldos) y, por cada
 * servicio caído, avisa a los superadministradores (campana, ALERTAS_CORREO y el webhook de
 * plataforma), máximo una vez por hora por cada tipo de problema.
 */
class VigilarSistema extends Command
{
    protected $signature = 'ruta:vigilar';

    protected $description = 'Chequea la salud de la app y sus servicios, y avisa lo que esté fallando';

    public function handle(): int
    {
        // Los trabajos fallidos solo se avisan cuando aumentan: los ya conocidos no se repiten.
        $fallidos = EstadoColas::fallidos();
        $antesFallidos = (int) Cache::get('vigilante.fallidos', 0);
        Cache::forever('vigilante.fallidos', $fallidos);

        $problemas = [];

        foreach (Salud::fallando() as $chequeo) {
            if ($chequeo['clave'] === 'trabajos_fallidos' && $fallidos <= $antesFallidos) {
                continue;
            }

            $problemas[$chequeo['clave']] = "{$chequeo['nombre']}: {$chequeo['detalle']}";
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

            AvisoPlataforma::enviar(AvisoPlataforma::SALUD, $mensaje, [
                'chequeo' => $tipo,
                'detalle' => $mensaje,
                'panel' => url('/admin/estado-sistema'),
            ]);
        }

        if (! $problemas) {
            $this->info('Todo en orden.');
        }

        return self::SUCCESS;
    }
}
