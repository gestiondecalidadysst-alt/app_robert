<?php

namespace App\Jobs;

use App\Actions\Transito\ConsultarTransito;
use App\Enums\TipoConsultaTransito;
use App\Models\Conductor;
use App\Models\Empresa;
use App\Models\Vehiculo;
use App\Support\Avisos;
use App\Support\Sistema\Colas;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/** Consulta automática de tránsito (fila baja). Si se alcanza el tope, se detiene y avisa una vez al mes. */
class ConsultarTransitoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 90;

    public function __construct(public string $clase, public int $id, public string $tipo)
    {
        $this->onQueue(Colas::BAJA);
    }

    public function handle(ConsultarTransito $consultar): void
    {
        /** @var Conductor|Vehiculo|null $sujeto */
        $sujeto = $this->clase::query()->withoutGlobalScopes()->find($this->id);
        if (! $sujeto || ! $sujeto->transito_auto) {
            return;
        }

        try {
            $consultar($sujeto, TipoConsultaTransito::from($this->tipo), 'automatica');
        } catch (ValidationException $e) {
            $mensaje = collect($e->errors())->flatten()->first();
            if (str_contains((string) $mensaje, 'tope') && Cache::add("transito.tope.{$sujeto->empresa_id}.".now()->format('Y-m'), true, now()->endOfMonth())) {
                $empresa = Empresa::find($sujeto->empresa_id);
                $empresa && Avisos::aQuienPueda($empresa, 'ver_consultas_transito',
                    Notification::make()->warning()->title('Consultas de tránsito detenidas')->body($mensaje));
            }
        }
    }
}
