<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo de consultas de tránsito (comparendos SIMIT, licencia RUNT, multas y RUNT del vehículo)
 * con un proveedor externo (PlacApi), revendido por la plataforma:
 * - proveedor_transito: una fila de la plataforma con la API key (cifrada), el costo real por
 *   crédito, los créditos por tipo y el precio de venta por defecto de cada tipo.
 * - empresas: el superadministrador habilita el módulo y puede fijar precios especiales; la empresa
 *   decide tipos, consultas automáticas (apagadas por defecto), frecuencia, tope mensual y bloqueos.
 * - consultas_transito: cada consulta con lo que se cobra al cliente y lo que cuesta (facturación).
 * - comparendos_transito: comparendos y multas de conductores y vehículos, con su historia.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('proveedor_transito')) {
            Schema::create('proveedor_transito', function (Blueprint $table) {
                $table->id();
                $table->string('proveedor', 30)->default('placapi');
                $table->string('url')->default('https://placapi.com');
                $table->text('api_key')->nullable(); // cifrada
                $table->boolean('activo')->default(false);
                $table->decimal('costo_credito', 10, 2)->default(349); // lo que cobra el proveedor por crédito
                $table->json('creditos')->nullable(); // créditos por tipo de consulta
                $table->json('precios')->nullable(); // precio de venta por defecto por tipo (COP)
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('empresas', 'transito_habilitado')) {
            Schema::table('empresas', function (Blueprint $table) {
                // Lo decide el superadministrador.
                $table->boolean('transito_habilitado')->default(false);
                $table->json('transito_precios')->nullable(); // precios especiales por tipo (vacío = los de la plataforma)
                // Lo decide la empresa.
                $table->json('transito_tipos')->nullable(); // tipos de consulta que usa
                $table->boolean('transito_auto')->default(false);
                $table->json('transito_auto_tipos')->nullable();
                $table->unsignedSmallInteger('transito_frecuencia_dias')->default(30);
                $table->decimal('transito_tope_mensual', 12, 2)->nullable();
                $table->boolean('transito_bloquear_licencia')->default(true);
                $table->boolean('transito_bloquear_comparendos')->default(false);
                $table->decimal('transito_bloquear_desde', 12, 2)->nullable();
            });
        }

        foreach (['conductores', 'vehiculos'] as $tabla) {
            if (! Schema::hasColumn($tabla, 'transito_auto')) {
                Schema::table($tabla, function (Blueprint $table) {
                    $table->boolean('transito_auto')->default(false); // incluido en las consultas automáticas
                    $table->json('transito_resumen')->nullable(); // último resultado (deuda, pendientes, licencia/RUNT)
                });
            }
        }

        if (! Schema::hasTable('consultas_transito')) {
            Schema::create('consultas_transito', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->string('tipo', 30); // conductor_comparendos | conductor_licencia | vehiculo_multas | vehiculo_runt
                $table->morphs('consultable'); // conductor o vehículo
                $table->string('referencia', 40); // cédula o placa consultada
                $table->string('origen', 12); // manual | automatica
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('estado', 12); // ok | sin_datos | error
                $table->unsignedSmallInteger('creditos')->default(0); // créditos que cobró el proveedor
                $table->boolean('cobrada')->default(false); // se le cobra al cliente
                $table->decimal('precio', 12, 2)->default(0); // lo que se le cobra al cliente
                $table->decimal('costo', 12, 2)->default(0); // lo que cuesta al proveedor
                $table->boolean('desde_cache')->default(false);
                $table->unsignedInteger('duracion_ms')->nullable();
                $table->json('respuesta')->nullable();
                $table->text('error')->nullable();
                $table->timestamps();

                $table->index(['empresa_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('comparendos_transito')) {
            Schema::create('comparendos_transito', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->morphs('consultable');
                $table->string('numero', 60);
                $table->date('fecha')->nullable();
                $table->string('codigo', 20)->nullable();
                $table->text('infraccion')->nullable();
                $table->decimal('valor', 14, 2)->default(0);
                $table->string('estado', 20); // pendiente | acuerdo | pagada | otro
                $table->string('tipo', 20)->nullable(); // comparendo | resolucion
                $table->string('organismo')->nullable();
                $table->string('placa', 10)->nullable();
                $table->timestamp('primera_vez_at');
                $table->timestamp('ultima_vez_at');
                $table->timestamp('ya_no_aparece_at')->nullable(); // dejó de salir en la consulta (pagado o depurado)
                $table->timestamps();

                $table->unique(['empresa_id', 'consultable_type', 'consultable_id', 'numero'], 'comparendos_transito_unico');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('comparendos_transito');
        Schema::dropIfExists('consultas_transito');
        foreach (['conductores', 'vehiculos'] as $tabla) {
            if (Schema::hasColumn($tabla, 'transito_auto')) {
                Schema::table($tabla, fn (Blueprint $table) => $table->dropColumn(['transito_auto', 'transito_resumen']));
            }
        }
        if (Schema::hasColumn('empresas', 'transito_habilitado')) {
            Schema::table('empresas', fn (Blueprint $table) => $table->dropColumn([
                'transito_habilitado', 'transito_precios', 'transito_tipos', 'transito_auto', 'transito_auto_tipos',
                'transito_frecuencia_dias', 'transito_tope_mensual', 'transito_bloquear_licencia',
                'transito_bloquear_comparendos', 'transito_bloquear_desde',
            ]));
        }
        Schema::dropIfExists('proveedor_transito');
    }
};
