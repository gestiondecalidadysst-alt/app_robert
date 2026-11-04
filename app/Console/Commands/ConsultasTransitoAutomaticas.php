<?php

namespace App\Console\Commands;

use App\Enums\TipoConsultaTransito;
use App\Jobs\ConsultarTransitoJob;
use App\Models\Conductor;
use App\Models\ConsultaTransito;
use App\Models\Empresa;
use App\Models\ProveedorTransito;
use App\Models\Vehiculo;
use Illuminate\Console\Command;

/**
 * Consultas automáticas de tránsito. Solo para empresas con el módulo habilitado que activaron las
 * automáticas; solo los tipos que eligieron y solo los conductores y vehículos marcados; cada uno
 * cuando su última consulta de ese tipo tiene más días que la frecuencia definida.
 */
class ConsultasTransitoAutomaticas extends Command
{
    protected $signature = 'ruta:consultas-transito';

    protected $description = 'Encola las consultas automáticas de tránsito que corresponden hoy';

    public function handle(): int
    {
        if (! ProveedorTransito::actual()->configurado()) {
            $this->info('Proveedor sin configurar.');

            return self::SUCCESS;
        }

        $encoladas = 0;
        Empresa::query()->where('activa', true)->where('transito_habilitado', true)->where('transito_auto', true)
            ->each(function (Empresa $empresa) use (&$encoladas): void {
                $tipos = array_intersect($empresa->transito_auto_tipos ?? [], $empresa->transito_tipos ?? []);
                $limite = now()->subDays(max(1, (int) $empresa->transito_frecuencia_dias));

                foreach ($tipos as $valor) {
                    $tipo = TipoConsultaTransito::tryFrom($valor);
                    if (! $tipo) {
                        continue;
                    }
                    $sujetos = $tipo->esDeConductor()
                        ? Conductor::query()->deEmpresa($empresa)->vinculados()->where('transito_auto', true)->get()
                        : Vehiculo::query()->deEmpresa($empresa)->enFlota()->where('transito_auto', true)->get();

                    foreach ($sujetos as $sujeto) {
                        $reciente = ConsultaTransito::query()->deEmpresa($empresa)->where('tipo', $tipo->value)
                            ->where('consultable_type', $sujeto->getMorphClass())->where('consultable_id', $sujeto->getKey())
                            ->whereIn('estado', ['ok', 'sin_datos'])->where('created_at', '>', $limite)->exists();
                        if (! $reciente) {
                            ConsultarTransitoJob::dispatch($sujeto::class, $sujeto->getKey(), $tipo->value);
                            $encoladas++;
                        }
                    }
                }
            });

        $this->info("Consultas encoladas: {$encoladas}");

        return self::SUCCESS;
    }
}
