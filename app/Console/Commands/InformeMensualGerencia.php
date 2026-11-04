<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Support\Gerencia\InformeGerencia;
use Illuminate\Console\Command;
use Throwable;

/**
 * Día 1 de cada mes: envía a gerencia el resumen del mes anterior (PDF por correo a quienes tienen
 * `ver_gerencia`, y el webhook `gerencia.informe_mensual` si la empresa lo eligió).
 */
class InformeMensualGerencia extends Command
{
    protected $signature = 'ruta:informe-gerencia';

    protected $description = 'Envía el resumen mensual de gerencia del mes anterior';

    public function handle(): int
    {
        Empresa::query()->where('activa', true)->each(function (Empresa $empresa): void {
            if (! $empresa->tieneModulo(\App\Enums\ModuloEmpresa::Gerencia)) {
                return;
            }
            try {
                $r = InformeGerencia::enviarMensual($empresa);
                $this->info("{$empresa->razon_social}: {$r['correos']} correo(s)" . ($r['webhook'] ? ' y webhook' : ''));
            } catch (Throwable $e) {
                report($e);
                $this->error("{$empresa->razon_social}: {$e->getMessage()}");
            }
        });

        return self::SUCCESS;
    }
}
