<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo 3 – Conductores: datos, seguridad social, comparendos, documentos con historial,
 * novedades de estado y asignación a vehículos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conductores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('tipo_identificacion_id')->nullable()->constrained('tipos_identificacion');
            $table->string('documento', 20);
            $table->string('nombres');
            $table->string('apellidos');
            $table->date('fecha_nacimiento')->nullable();
            $table->string('genero', 1)->nullable(); // F | M | O
            $table->string('grupo_sanguineo', 3)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('direccion')->nullable();
            $table->foreignId('municipio_id')->nullable()->constrained('municipios');
            $table->string('foto_path')->nullable();
            $table->foreignId('nivel_academico_id')->nullable()->constrained('niveles_academicos');
            $table->foreignId('oficina_id')->nullable()->constrained('oficinas')->nullOnDelete();
            $table->date('fecha_ingreso')->nullable();

            // Seguridad social
            $table->foreignId('eps_id')->nullable()->constrained('eps');
            $table->foreignId('arl_id')->nullable()->constrained('arl');
            $table->string('fondo_pensiones')->nullable();

            // Contacto de emergencia
            $table->string('contacto_emergencia')->nullable();
            $table->string('telefono_emergencia', 30)->nullable();

            // Comparendos (última consulta en el SIMIT)
            $table->string('comparendos_estado', 20)->nullable();
            $table->unsignedSmallInteger('comparendos_cantidad')->default(0);
            $table->date('comparendos_consultado_el')->nullable();

            // Estado (lo cambia App\Actions\Conductores\CambiarEstadoConductor)
            $table->string('estado', 20)->default('activo'); // activo | suspendido | retirado
            $table->date('estado_desde')->nullable();

            // Usuario del portal del conductor (módulo 9)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['empresa_id', 'documento']);
            $table->index(['empresa_id', 'estado']);
        });

        Schema::create('documentos_conductor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('conductor_id')->constrained('conductores')->cascadeOnDelete();
            $table->string('tipo', 30); // App\Enums\TipoDocumentoConductor
            $table->string('numero', 60)->nullable();
            $table->foreignId('categoria_licencia_id')->nullable()->constrained('categorias_licencia');
            $table->string('entidad')->nullable();
            $table->date('fecha_expedicion')->nullable();
            $table->date('fecha_vencimiento');
            $table->string('archivo_path')->nullable();
            $table->string('archivo_nombre')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['conductor_id', 'tipo', 'fecha_vencimiento']);
            $table->index(['empresa_id', 'fecha_vencimiento']);
        });

        Schema::create('novedades_conductor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('conductor_id')->constrained('conductores')->cascadeOnDelete();
            $table->string('tipo', 20); // suspension | retiro | reactivacion | reintegro
            $table->date('fecha');
            $table->string('estado_anterior', 20)->nullable();
            $table->string('estado_nuevo', 20);
            $table->date('fecha_reactivacion')->nullable();
            $table->text('observacion');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['conductor_id', 'fecha']);
        });

        // Qué conductores manejan qué vehículos. Vigente = sin fecha_hasta.
        Schema::create('asignaciones_vehiculo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('conductor_id')->constrained('conductores')->cascadeOnDelete();
            $table->foreignId('vehiculo_id')->constrained('vehiculos')->cascadeOnDelete();
            $table->date('fecha_desde');
            $table->date('fecha_hasta')->nullable();
            $table->boolean('principal')->default(false);
            $table->string('observacion')->nullable();
            $table->timestamps();

            $table->index(['vehiculo_id', 'fecha_hasta']);
            $table->index(['conductor_id', 'fecha_hasta']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignaciones_vehiculo');
        Schema::dropIfExists('novedades_conductor');
        Schema::dropIfExists('documentos_conductor');
        Schema::dropIfExists('conductores');
    }
};
