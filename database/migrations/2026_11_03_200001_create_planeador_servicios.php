<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Planeador de servicios (PESV, Res. 40595/2022, paso 15 «Planificación de desplazamientos laborales»
 * y programa de prevención de la fatiga del paso 8):
 * - rutas: rutogramas reutilizables por origen y destino (puntos críticos, paradas seguras,
 *   velocidades, rutas alternas, contactos de emergencia).
 * - servicios: el plan de cada desplazamiento. Puede crearse antes del FUEC (y desde él generar el
 *   extracto) o nacer al expedir un FUEC. Un FUEC = un servicio.
 * - eventos_servicio: lectura del plan por el conductor y, si quiere, inicio, paradas, novedades y fin.
 * - empresas: política de jornada (solo genera avisos) y antelación esperada de la planificación.
 *
 * Idempotente. Crea el servicio de cada FUEC vigente o programado que ya existía.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('empresas', 'jornada_max_horas_dia')) {
            Schema::table('empresas', function (Blueprint $table) {
                $table->unsignedTinyInteger('jornada_max_horas_dia')->default(10);
                $table->unsignedTinyInteger('jornada_max_horas_continuas')->default(4);
                $table->unsignedTinyInteger('jornada_descanso_horas')->default(8);
                $table->unsignedSmallInteger('planeacion_antelacion_horas')->default(24);
            });
        }

        if (! Schema::hasTable('rutas')) {
            Schema::create('rutas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->string('nombre');
                $table->foreignId('origen_municipio_id')->constrained('municipios');
                $table->foreignId('destino_municipio_id')->constrained('municipios');
                $table->boolean('ida_y_vuelta')->default(true); // también sirve para destino → origen
                $table->text('recorrido')->nullable(); // vías por las que va
                $table->unsignedInteger('distancia_km')->nullable();
                $table->unsignedInteger('duracion_min')->nullable(); // tiempo de conducción estimado
                $table->unsignedSmallInteger('velocidad_maxima')->nullable();
                $table->json('puntos')->nullable(); // [{tipo, nombre, referencia, velocidad, indicacion}]
                $table->text('rutas_alternas')->nullable();
                $table->text('recomendaciones')->nullable();
                $table->text('contactos_emergencia')->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();

                $table->index(['empresa_id', 'origen_municipio_id', 'destino_municipio_id'], 'rutas_empresa_origen_destino_index');
            });
        }

        if (! Schema::hasTable('servicios')) {
            Schema::create('servicios', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('contrato_id')->nullable()->constrained('contratos')->nullOnDelete();
                $table->foreignId('fuec_id')->nullable()->unique()->constrained('fuecs')->nullOnDelete();
                $table->foreignId('ruta_id')->nullable()->constrained('rutas')->nullOnDelete();
                $table->foreignId('vehiculo_id')->nullable()->constrained('vehiculos')->nullOnDelete();
                $table->date('fecha_inicio');
                $table->date('fecha_fin');
                $table->time('hora_salida')->nullable();
                $table->time('hora_llegada')->nullable();
                $table->foreignId('origen_municipio_id')->constrained('municipios');
                $table->foreignId('destino_municipio_id')->constrained('municipios');
                $table->text('recorrido')->nullable();
                $table->unsignedInteger('conduccion_min')->nullable(); // conducción estimada por día
                $table->unsignedSmallInteger('pasajeros')->nullable();
                $table->text('observaciones')->nullable();
                $table->string('estado', 20)->default('planificado'); // planificado|con_extracto|en_curso|finalizado|cancelado
                $table->text('motivo_cancelacion')->nullable();
                $table->timestamp('leido_at')->nullable();
                $table->foreignId('leido_por')->nullable()->constrained('conductores')->nullOnDelete();
                $table->timestamp('iniciado_at')->nullable();
                $table->timestamp('finalizado_at')->nullable();
                $table->unsignedInteger('km_inicial')->nullable();
                $table->unsignedInteger('km_final')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['empresa_id', 'fecha_inicio', 'fecha_fin']);
                $table->index(['vehiculo_id', 'fecha_inicio']);
            });
        }

        if (! Schema::hasTable('servicio_conductor')) {
            Schema::create('servicio_conductor', function (Blueprint $table) {
                $table->foreignId('servicio_id')->constrained('servicios')->cascadeOnDelete();
                $table->foreignId('conductor_id')->constrained('conductores')->cascadeOnDelete();
                $table->unsignedTinyInteger('orden')->default(1);
                $table->primary(['servicio_id', 'conductor_id']);
            });
        }

        if (! Schema::hasTable('eventos_servicio')) {
            Schema::create('eventos_servicio', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('servicio_id')->constrained('servicios')->cascadeOnDelete();
                $table->foreignId('conductor_id')->nullable()->constrained('conductores')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('tipo', 20); // leido|inicio|parada|novedad|fin
                $table->text('nota')->nullable();
                $table->unsignedInteger('kilometraje')->nullable();
                $table->decimal('latitud', 10, 7)->nullable();
                $table->decimal('longitud', 10, 7)->nullable();
                $table->timestamps();

                $table->index(['servicio_id', 'created_at']);
            });
        }

        $this->serviciosDeFuecExistentes();
    }

    /** Cada FUEC vigente o programado sin servicio recibe el suyo (aparece en el planeador). */
    private function serviciosDeFuecExistentes(): void
    {
        $fuecs = DB::table('fuecs')->whereNull('anulado_at')->whereDate('fecha_fin', '>=', now()->toDateString())
            ->whereNotIn('id', DB::table('servicios')->whereNotNull('fuec_id')->select('fuec_id'))->get();

        foreach ($fuecs as $f) {
            $id = DB::table('servicios')->insertGetId([
                'empresa_id' => $f->empresa_id,
                'contrato_id' => $f->contrato_id,
                'fuec_id' => $f->id,
                'vehiculo_id' => $f->vehiculo_id,
                'fecha_inicio' => $f->fecha_inicio,
                'fecha_fin' => $f->fecha_fin,
                'hora_salida' => $f->hora_inicio ?? null,
                'hora_llegada' => $f->hora_fin ?? null,
                'origen_municipio_id' => $f->origen_municipio_id,
                'destino_municipio_id' => $f->destino_municipio_id,
                'recorrido' => $f->recorrido,
                'estado' => 'con_extracto',
                'user_id' => $f->user_id,
                'created_at' => $f->created_at,
                'updated_at' => now(),
            ]);

            foreach (DB::table('fuec_conductor')->where('fuec_id', $f->id)->get() as $c) {
                DB::table('servicio_conductor')->insert(['servicio_id' => $id, 'conductor_id' => $c->conductor_id, 'orden' => $c->orden ?? 1]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_servicio');
        Schema::dropIfExists('servicio_conductor');
        Schema::dropIfExists('servicios');
        Schema::dropIfExists('rutas');

        if (Schema::hasColumn('empresas', 'jornada_max_horas_dia')) {
            Schema::table('empresas', function (Blueprint $table) {
                $table->dropColumn(['jornada_max_horas_dia', 'jornada_max_horas_continuas', 'jornada_descanso_horas', 'planeacion_antelacion_horas']);
            });
        }
    }
};
