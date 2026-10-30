<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo 2 – Flota: propietarios, vehículos, documentos con historial y novedades de estado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('propietarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('tipo_identificacion_id')->nullable()->constrained('tipos_identificacion');
            $table->string('documento', 20);
            $table->string('nombres');
            $table->string('apellidos')->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('genero', 1)->nullable(); // F | M | O
            $table->date('fecha_ingreso')->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('direccion')->nullable();
            $table->foreignId('municipio_id')->nullable()->constrained('municipios');
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['empresa_id', 'documento']);
        });

        Schema::create('vehiculos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('placa', 6);
            $table->string('numero_interno', 10)->nullable();
            $table->foreignId('propietario_id')->constrained('propietarios');

            // Tarjeta de propiedad (licencia de tránsito)
            $table->foreignId('marca_id')->constrained('marcas');
            $table->foreignId('linea_id')->nullable()->constrained('lineas');
            $table->unsignedSmallInteger('modelo');
            $table->foreignId('clase_vehiculo_id')->constrained('clases_vehiculo');
            $table->foreignId('carroceria_id')->nullable()->constrained('carrocerias');
            $table->foreignId('combustible_id')->nullable()->constrained('combustibles');
            $table->foreignId('tipo_servicio_id')->nullable()->constrained('tipos_servicio');
            $table->string('color', 60)->nullable();
            $table->unsignedInteger('cilindraje')->nullable();
            $table->unsignedSmallInteger('capacidad_pasajeros')->nullable();
            $table->unsignedInteger('capacidad_kg')->nullable();
            $table->unsignedTinyInteger('puertas')->nullable();
            $table->string('numero_motor', 40)->nullable();
            $table->string('vin', 40)->nullable();
            $table->string('numero_chasis', 40)->nullable();
            $table->date('fecha_matricula')->nullable();
            $table->foreignId('organismo_transito_id')->nullable()->constrained('municipios');
            $table->string('foto_path')->nullable();

            // Ficha técnica (potencia, ejes, frenos, llantas, suspensión…): no se consulta por columnas.
            $table->json('ficha_tecnica')->nullable();

            // Vinculación con la empresa
            $table->string('tipo_vinculacion', 30)->nullable(); // convenio_empresarial | vinculacion_flota
            $table->foreignId('empresa_convenio_id')->nullable()->constrained('empresas_convenio')->nullOnDelete();
            $table->date('fecha_vinculacion')->nullable();

            // Estado operativo (lo cambia App\Actions\Flota\CambiarEstadoVehiculo)
            $table->string('estado', 20)->default('operando'); // operando | suspendido | taller | desvinculado
            $table->date('estado_desde')->nullable();

            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['empresa_id', 'placa']);
            $table->unique(['empresa_id', 'numero_interno']);
            $table->index(['empresa_id', 'estado']);
        });

        // Un registro por cada documento expedido: renovar = registro nuevo, así queda el historial.
        Schema::create('documentos_vehiculo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('vehiculo_id')->constrained('vehiculos')->cascadeOnDelete();
            $table->string('tipo', 30); // App\Enums\TipoDocumentoVehiculo
            $table->string('numero', 60)->nullable();
            $table->foreignId('aseguradora_id')->nullable()->constrained('aseguradoras');
            $table->string('entidad')->nullable(); // CDA, taller, organismo que expide
            $table->date('fecha_expedicion')->nullable();
            $table->date('fecha_vencimiento');
            $table->string('archivo_path')->nullable();
            $table->string('archivo_nombre')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vehiculo_id', 'tipo', 'fecha_vencimiento']);
            $table->index(['empresa_id', 'fecha_vencimiento']);
        });

        Schema::create('novedades_vehiculo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('vehiculo_id')->constrained('vehiculos')->cascadeOnDelete();
            $table->string('tipo', 20); // suspension | taller | desvinculacion | reactivacion | vinculacion
            $table->date('fecha');
            $table->string('estado_anterior', 20)->nullable();
            $table->string('estado_nuevo', 20);
            $table->date('fecha_reactivacion')->nullable(); // prevista, para suspensión o taller
            $table->text('observacion');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['vehiculo_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('novedades_vehiculo');
        Schema::dropIfExists('documentos_vehiculo');
        Schema::dropIfExists('vehiculos');
        Schema::dropIfExists('propietarios');
    }
};
