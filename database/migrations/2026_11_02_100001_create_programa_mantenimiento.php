<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Programa de mantenimiento (Módulo 6, segunda parte):
 * - plantillas_mantenimiento: planes modelo por clase de vehículo, por marca y línea, o libres.
 *   Las rutinas existentes pasan a la plantilla "Plan general" (por defecto) de cada empresa.
 * - Plan del vehículo: sigue una plantilla o tiene sus propias rutinas (personalizado).
 * - programas_mantenimiento + actividades_mantenimiento: programa anual aprobado con trazabilidad
 *   (programado vs. ejecutado, reprogramaciones y cancelaciones con motivo).
 * - mantenimientos.registro_inicial: punto de partida del plan (no cuenta como mantenimiento real).
 * - Webhooks (n8n) de la empresa y su historial de envíos.
 *
 * Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plantillas_mantenimiento')) {
            Schema::create('plantillas_mantenimiento', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->string('nombre');
                $table->string('criterio', 15)->default('libre'); // clase | marca_linea | libre
                $table->foreignId('clase_vehiculo_id')->nullable()->constrained('clases_vehiculo')->nullOnDelete();
                $table->foreignId('marca_id')->nullable()->constrained('marcas')->nullOnDelete();
                $table->foreignId('linea_id')->nullable()->constrained('lineas')->nullOnDelete();
                $table->text('descripcion')->nullable();
                $table->boolean('por_defecto')->default(false);
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('rutinas_mantenimiento', 'plantilla_mantenimiento_id')) {
            Schema::table('rutinas_mantenimiento', function (Blueprint $table) {
                $table->foreignId('plantilla_mantenimiento_id')->nullable()->after('empresa_id')
                    ->constrained('plantillas_mantenimiento')->cascadeOnDelete();
            });
        }

        if (! Schema::hasColumn('vehiculos', 'plantilla_mantenimiento_id')) {
            Schema::table('vehiculos', function (Blueprint $table) {
                $table->foreignId('plantilla_mantenimiento_id')->nullable()->constrained('plantillas_mantenimiento')->nullOnDelete();
                $table->boolean('plan_personalizado')->default(false);
            });
        }

        if (! Schema::hasColumn('mantenimientos', 'registro_inicial')) {
            Schema::table('mantenimientos', function (Blueprint $table) {
                $table->boolean('registro_inicial')->default(false)->after('tipo');
            });
        }

        if (! Schema::hasColumn('empresas', 'km_diarios_defecto')) {
            Schema::table('empresas', function (Blueprint $table) {
                $table->unsignedSmallInteger('km_diarios_defecto')->default(150);
                $table->unsignedTinyInteger('mantenimiento_dias_aviso')->default(7);
                $table->boolean('webhook_activo')->default(false);
                $table->text('webhook_url')->nullable(); // cifrada
                $table->text('webhook_secreto')->nullable(); // cifrado
                $table->json('webhook_eventos')->nullable();
            });
        }

        if (! Schema::hasTable('programas_mantenimiento')) {
            Schema::create('programas_mantenimiento', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->unsignedSmallInteger('anio');
                $table->unsignedSmallInteger('version')->default(1);
                $table->string('estado', 15)->default('borrador'); // borrador | aprobado | reemplazado
                $table->string('codigo_formato', 40)->nullable();
                $table->unsignedSmallInteger('km_diarios_defecto')->default(150);
                $table->text('observaciones')->nullable();
                $table->foreignId('elaborado_por')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('elaborado_at')->nullable();
                $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('aprobado_at')->nullable();
                $table->timestamps();

                $table->unique(['empresa_id', 'anio', 'version'], 'programas_mtto_empresa_anio_version_unique');
            });
        }

        if (! Schema::hasTable('actividades_mantenimiento')) {
            Schema::create('actividades_mantenimiento', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('programa_mantenimiento_id')->constrained('programas_mantenimiento')->cascadeOnDelete();
                $table->foreignId('vehiculo_id')->constrained('vehiculos');
                $table->foreignId('rutina_mantenimiento_id')->nullable()->constrained('rutinas_mantenimiento')->nullOnDelete();
                $table->string('rutina_nombre');
                $table->string('frecuencia')->nullable();
                $table->date('fecha_original'); // línea base aprobada (no cambia)
                $table->unsignedInteger('km_original')->nullable();
                $table->date('fecha_programada');
                $table->unsignedInteger('km_programado')->nullable();
                $table->string('estado', 15)->default('programada'); // programada | ejecutada | cancelada
                $table->foreignId('mantenimiento_id')->nullable()->constrained('mantenimientos')->nullOnDelete();
                $table->date('fecha_ejecucion')->nullable();
                $table->unsignedInteger('km_ejecucion')->nullable();
                $table->boolean('incorporada')->default(false); // agregada después de aprobar el programa
                $table->text('motivo')->nullable();
                $table->json('historial')->nullable();
                $table->timestamp('avisado_proximo_at')->nullable();
                $table->timestamp('avisado_vencido_at')->nullable();
                $table->timestamps();

                $table->index(['programa_mantenimiento_id', 'vehiculo_id'], 'actividades_mtto_programa_vehiculo_index');
                $table->index(['empresa_id', 'fecha_programada'], 'actividades_mtto_empresa_fecha_index');
            });
        }

        if (! Schema::hasTable('webhook_entregas')) {
            Schema::create('webhook_entregas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->string('evento', 60);
                $table->json('payload');
                $table->string('estado', 15)->default('pendiente'); // pendiente | enviado | fallido
                $table->unsignedSmallInteger('codigo_respuesta')->nullable();
                $table->text('error')->nullable();
                $table->unsignedTinyInteger('intentos')->default(0);
                $table->timestamp('enviado_at')->nullable();
                $table->timestamps();

                $table->index(['empresa_id', 'created_at']);
            });
        }

        $this->migrarRutinas();
    }

    /** Las rutinas de la primera versión (flota / clase / vehículo) pasan a plantillas y planes de vehículo. */
    private function migrarRutinas(): void
    {
        $ahora = now();

        foreach (DB::table('empresas')->pluck('id') as $empresaId) {
            $rutinas = DB::table('rutinas_mantenimiento')->where('empresa_id', $empresaId)->whereNull('plantilla_mantenimiento_id')->get();
            if ($rutinas->isEmpty()) {
                continue;
            }

            $general = DB::table('plantillas_mantenimiento')->where('empresa_id', $empresaId)->where('por_defecto', true)->value('id')
                ?? DB::table('plantillas_mantenimiento')->insertGetId([
                    'empresa_id' => $empresaId, 'nombre' => 'Plan general', 'criterio' => 'libre', 'por_defecto' => true, 'activo' => true,
                    'descripcion' => 'Plan para los vehículos que no tienen una plantilla más específica.',
                    'created_at' => $ahora, 'updated_at' => $ahora,
                ]);

            $flota = $rutinas->whereNull('vehiculo_id')->whereNull('clase_vehiculo_id');
            DB::table('rutinas_mantenimiento')->whereIn('id', $flota->pluck('id'))->update(['plantilla_mantenimiento_id' => $general]);

            $clave = fn ($r) => mb_strtolower(trim($r->nombre));
            $copiar = function ($r, array $destino) use ($ahora) {
                DB::table('rutinas_mantenimiento')->insert([
                    'empresa_id' => $r->empresa_id, 'nombre' => $r->nombre, 'intervalo_km' => $r->intervalo_km,
                    'intervalo_meses' => $r->intervalo_meses, 'descripcion' => $r->descripcion, 'orden' => $r->orden,
                    'activo' => $r->activo, 'created_at' => $ahora, 'updated_at' => $ahora, ...$destino,
                ]);
            };

            // Rutinas por clase → plantilla de esa clase (rutinas generales + las de la clase).
            $plantillaClase = [];
            foreach ($rutinas->whereNull('vehiculo_id')->whereNotNull('clase_vehiculo_id')->groupBy('clase_vehiculo_id') as $claseId => $propias) {
                $nombreClase = DB::table('clases_vehiculo')->where('id', $claseId)->value('nombre') ?? 'clase';
                $id = DB::table('plantillas_mantenimiento')->insertGetId([
                    'empresa_id' => $empresaId, 'nombre' => "Plan {$nombreClase}", 'criterio' => 'clase', 'clase_vehiculo_id' => $claseId,
                    'por_defecto' => false, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora,
                ]);
                $plantillaClase[$claseId] = $id;
                $reemplazadas = $propias->map($clave)->all();
                foreach ($flota->reject(fn ($r) => in_array($clave($r), $reemplazadas, true)) as $r) {
                    $copiar($r, ['plantilla_mantenimiento_id' => $id]);
                }
                DB::table('rutinas_mantenimiento')->whereIn('id', $propias->pluck('id'))
                    ->update(['plantilla_mantenimiento_id' => $id, 'clase_vehiculo_id' => null]);
            }

            // Rutinas de un vehículo → plan personalizado (su plantilla + las del vehículo).
            foreach ($rutinas->whereNotNull('vehiculo_id')->groupBy('vehiculo_id') as $vehiculoId => $propias) {
                $claseId = DB::table('vehiculos')->where('id', $vehiculoId)->value('clase_vehiculo_id');
                $base = $plantillaClase[$claseId] ?? $general;
                $reemplazadas = $propias->map($clave)->all();
                foreach (DB::table('rutinas_mantenimiento')->where('plantilla_mantenimiento_id', $base)->get() as $r) {
                    if (! in_array($clave($r), $reemplazadas, true)) {
                        $copiar($r, ['vehiculo_id' => $vehiculoId, 'plantilla_mantenimiento_id' => null]);
                    }
                }
                DB::table('rutinas_mantenimiento')->whereIn('id', $propias->pluck('id'))->update(['clase_vehiculo_id' => null]);
                DB::table('vehiculos')->where('id', $vehiculoId)->update(['plan_personalizado' => true, 'plantilla_mantenimiento_id' => $base]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_entregas');
        Schema::dropIfExists('actividades_mantenimiento');
        Schema::dropIfExists('programas_mantenimiento');

        foreach ([
            'empresas' => ['km_diarios_defecto', 'mantenimiento_dias_aviso', 'webhook_activo', 'webhook_url', 'webhook_secreto', 'webhook_eventos'],
            'mantenimientos' => ['registro_inicial'],
        ] as $tabla => $columnas) {
            $existentes = array_values(array_filter($columnas, fn ($c) => Schema::hasColumn($tabla, $c)));
            if ($existentes) {
                Schema::table($tabla, fn (Blueprint $t) => $t->dropColumn($existentes));
            }
        }
        if (Schema::hasColumn('vehiculos', 'plantilla_mantenimiento_id')) {
            Schema::table('vehiculos', function (Blueprint $table) {
                $table->dropConstrainedForeignId('plantilla_mantenimiento_id');
                $table->dropColumn('plan_personalizado');
            });
        }
        if (Schema::hasColumn('rutinas_mantenimiento', 'plantilla_mantenimiento_id')) {
            Schema::table('rutinas_mantenimiento', fn (Blueprint $t) => $t->dropConstrainedForeignId('plantilla_mantenimiento_id'));
        }
        Schema::dropIfExists('plantillas_mantenimiento');
    }
};
