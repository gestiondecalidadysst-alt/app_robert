<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo 6 – Mantenimiento:
 * - rutinas_mantenimiento: plan preventivo por kilometraje y tiempo (empresa, clase o vehículo).
 * - mantenimientos: lo que se le hizo al vehículo (lo hace el propietario; lo registran el auxiliar,
 *   el rol Mantenimiento o el conductor desde el portal, con evidencias).
 * - planes_accion: seguimiento de la empresa a fallas de preoperacionales, inspecciones y rutinas vencidas.
 * - evidencias: archivos adjuntos (facturas, fotos) de mantenimientos y planes de acción.
 *
 * Idempotente: se puede volver a correr sobre un despliegue que falló a medias.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rutinas_mantenimiento')) {
            Schema::create('rutinas_mantenimiento', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->string('nombre');
                $table->foreignId('clase_vehiculo_id')->nullable()->constrained('clases_vehiculo')->nullOnDelete(); // vacía = todas
                $table->foreignId('vehiculo_id')->nullable()->constrained('vehiculos')->cascadeOnDelete(); // propia del vehículo
                $table->unsignedInteger('intervalo_km')->nullable();
                $table->unsignedSmallInteger('intervalo_meses')->nullable();
                $table->text('descripcion')->nullable();
                $table->unsignedSmallInteger('orden')->default(0);
                $table->boolean('activo')->default(true);
                $table->timestamps();

                $table->index(['empresa_id', 'activo']);
            });
        }

        if (! Schema::hasTable('planes_accion')) {
            Schema::create('planes_accion', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('vehiculo_id')->constrained('vehiculos');
                $table->string('origen', 20); // preoperacional | inspeccion | preventivo | manual
                $table->foreignId('inspeccion_id')->nullable()->constrained('inspecciones')->nullOnDelete();
                $table->foreignId('rutina_mantenimiento_id')->nullable()->constrained('rutinas_mantenimiento')->nullOnDelete();
                $table->string('prioridad', 10); // alta | media | baja
                $table->string('titulo');
                $table->json('hallazgos')->nullable(); // fallas: descripción, crítico, observación, fecha, reincidencias
                $table->text('accion')->nullable(); // qué hay que hacer
                $table->string('responsable')->nullable(); // por defecto el propietario
                $table->date('fecha_limite')->nullable();
                $table->string('estado', 20)->default('abierto'); // abierto | en_ejecucion | por_verificar | cerrado | rechazado
                $table->unsignedSmallInteger('reincidencias')->default(0);
                $table->text('observacion_cierre')->nullable();
                $table->timestamp('cerrado_at')->nullable();
                $table->foreignId('cerrado_por')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['empresa_id', 'estado']);
                $table->index(['vehiculo_id', 'estado']);
            });
        }

        if (! Schema::hasTable('mantenimientos')) {
            Schema::create('mantenimientos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('vehiculo_id')->constrained('vehiculos');
                $table->date('fecha');
                $table->unsignedInteger('kilometraje')->nullable();
                $table->string('tipo', 15); // preventivo | correctivo
                $table->string('accion', 15); // cambio | ajuste | revision | reparacion
                $table->text('actividad');
                $table->text('problema')->nullable();
                $table->text('repuestos')->nullable();
                $table->string('taller')->nullable();
                $table->string('responsable_documento', 30)->nullable();
                $table->string('responsable_nombre')->nullable();
                $table->decimal('valor', 14, 2)->nullable();
                $table->string('factura_numero', 60)->nullable();
                $table->foreignId('plan_accion_id')->nullable()->constrained('planes_accion')->nullOnDelete();
                $table->foreignId('conductor_id')->nullable()->constrained('conductores')->nullOnDelete(); // si lo registró el conductor
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('anulado_at')->nullable();
                $table->text('motivo_anulacion')->nullable();
                $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['empresa_id', 'fecha']);
                $table->index(['vehiculo_id', 'fecha']);
            });
        }

        if (! Schema::hasTable('mantenimiento_rutina')) {
            Schema::create('mantenimiento_rutina', function (Blueprint $table) {
                $table->foreignId('mantenimiento_id')->constrained('mantenimientos')->cascadeOnDelete();
                $table->foreignId('rutina_mantenimiento_id')->constrained('rutinas_mantenimiento')->cascadeOnDelete();
                $table->primary(['mantenimiento_id', 'rutina_mantenimiento_id']);
            });
        }

        if (! Schema::hasTable('evidencias')) {
            Schema::create('evidencias', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->morphs('adjuntable'); // mantenimiento o plan de acción
                $table->string('path'); // disco privado
                $table->string('nombre');
                $table->string('mime', 100)->nullable();
                $table->unsignedInteger('tamano')->default(0);
                $table->string('descripcion')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('evidencias');
        Schema::dropIfExists('mantenimiento_rutina');
        Schema::dropIfExists('mantenimientos');
        Schema::dropIfExists('planes_accion');
        Schema::dropIfExists('rutinas_mantenimiento');
    }
};
