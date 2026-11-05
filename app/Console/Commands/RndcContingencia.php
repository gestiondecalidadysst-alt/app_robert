<?php

namespace App\Console\Commands;

use App\Jobs\ReintentarEnvioRndc;
use App\Models\RegistroRndc;
use Illuminate\Console\Command;

/**
 * Red de seguridad de la contingencia del RNDC.
 *
 * El reintento lo programa la propia cola, pero si la cola se pierde (reinicio del
 * contenedor, worker caído) el envío quedaría esperando para siempre. Esta tarea
 * recoge lo que ya cumplió su hora y lo vuelve a encolar.
 */
class RndcContingencia extends Command
{
    protected $signature = 'ruta:rndc-contingencia';

    protected $description = 'Reencola los envíos al RNDC que quedaron en contingencia';

    public function handle(): int
    {
        $pendientes = RegistroRndc::query()->withoutGlobalScopes()
            ->enContingencia()
            ->where(fn ($query) => $query->whereNull('reintentar_a')->orWhere('reintentar_a', '<=', now()))
            ->orderBy('id')->limit(200)->get();

        foreach ($pendientes as $registro) {
            ReintentarEnvioRndc::dispatch($registro->id)->onQueue('baja');
        }

        $this->info($pendientes->count().' envío(s) reencolados.');

        return self::SUCCESS;
    }
}
