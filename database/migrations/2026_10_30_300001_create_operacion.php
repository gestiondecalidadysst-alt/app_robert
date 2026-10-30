<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo 4 – Operación: clientes (contratantes), contratos, pagos y FUEC
 * (Formato Único de Extracto del Contrato, Resolución 6652 de 2019).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('tipo_identificacion_id')->nullable()->constrained('tipos_identificacion');
            $table->string('documento', 20);
            $table->unsignedTinyInteger('dv')->nullable(); // solo NIT
            $table->string('nombre'); // razón social o nombre completo
            $table->foreignId('expedicion_municipio_id')->nullable()->constrained('municipios'); // cédula expedida en
            $table->string('direccion')->nullable();
            $table->foreignId('municipio_id')->nullable()->constrained('municipios');
            $table->string('telefono', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('contacto')->nullable(); // persona de contacto o representante
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['empresa_id', 'documento']);
        });

        Schema::create('contratos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->unsignedInteger('numero'); // consecutivo por empresa
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->string('objeto', 30); // App\Enums\ObjetoContrato
            $table->text('descripcion_objeto')->nullable();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fin')->nullable();
            $table->foreignId('origen_municipio_id')->nullable()->constrained('municipios');
            $table->foreignId('destino_municipio_id')->nullable()->constrained('municipios');
            $table->text('recorrido')->nullable();
            $table->boolean('disposicion_total')->default(false); // art. 9 Res. 6652/2019
            $table->text('zonas_disposicion')->nullable();
            $table->unsignedSmallInteger('cantidad_vehiculos')->nullable();
            $table->unsignedSmallInteger('cantidad_pasajeros')->nullable();

            // Responsable del contrato (por parte del contratante)
            $table->string('responsable_nombre')->nullable();
            $table->string('responsable_documento', 20)->nullable();
            $table->string('responsable_telefono', 30)->nullable();
            $table->string('responsable_direccion')->nullable();

            // Valor
            $table->decimal('valor', 14, 2)->default(0);
            $table->string('forma_pago', 20)->default('contado'); // contado | credito
            $table->date('fecha_limite_pago')->nullable();

            $table->foreignId('oficina_id')->nullable()->constrained('oficinas')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observaciones')->nullable();

            // Anulación (no se borran)
            $table->timestamp('anulado_at')->nullable();
            $table->text('motivo_anulacion')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['empresa_id', 'numero']);
            $table->index(['empresa_id', 'fecha_fin']);
        });

        Schema::create('pagos_contrato', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('contrato_id')->constrained('contratos')->cascadeOnDelete();
            $table->date('fecha');
            $table->decimal('valor', 14, 2);
            $table->string('metodo', 20); // efectivo | transferencia | tarjeta | otro
            $table->string('referencia')->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['contrato_id', 'fecha']);
        });

        Schema::create('fuecs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('contrato_id')->constrained('contratos');
            $table->unsignedInteger('consecutivo'); // por contrato (art. 4 f)
            $table->string('numero', 21); // 21 dígitos (art. 4)
            $table->foreignId('vehiculo_id')->constrained('vehiculos');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->foreignId('origen_municipio_id')->constrained('municipios');
            $table->foreignId('destino_municipio_id')->constrained('municipios');
            $table->text('recorrido')->nullable();
            $table->foreignId('empresa_convenio_id')->nullable()->constrained('empresas_convenio')->nullOnDelete(); // art. 13

            // Copia de los datos al momento de expedir (el documento no cambia aunque cambien los maestros).
            $table->json('datos');

            $table->string('codigo_verificacion', 32)->unique(); // para el QR de verificación pública
            $table->foreignId('oficina_id')->nullable()->constrained('oficinas')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Anulación (no se borran)
            $table->timestamp('anulado_at')->nullable();
            $table->text('motivo_anulacion')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['contrato_id', 'consecutivo']);
            $table->index(['empresa_id', 'numero']);
            $table->index(['vehiculo_id', 'fecha_inicio']);
        });

        Schema::create('fuec_conductor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fuec_id')->constrained('fuecs')->cascadeOnDelete();
            $table->foreignId('conductor_id')->constrained('conductores');
            $table->unsignedTinyInteger('orden'); // 1, 2, 3

            $table->unique(['fuec_id', 'conductor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuec_conductor');
        Schema::dropIfExists('fuecs');
        Schema::dropIfExists('pagos_contrato');
        Schema::dropIfExists('contratos');
        Schema::dropIfExists('clientes');
    }
};
