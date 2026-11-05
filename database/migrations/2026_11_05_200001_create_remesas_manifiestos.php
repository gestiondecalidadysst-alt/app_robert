<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo de carga, fase 2: remesa terrestre de carga (proceso 3 del RNDC) y manifiesto electrónico
 * de carga (proceso 4). Se envían con todos sus datos, sin información de carga ni de viaje previas
 * (opcionales en el Manual RNDC Web-Service v2.3). El radicado queda en registros_rndc.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('manifiestos_carga')) {
            Schema::create('manifiestos_carga', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->unsignedInteger('numero'); // consecutivo único por empresa
                $table->string('tipo', 1)->default('G'); // G general, P paqueteo, Y contenedores, V consolidadores, C contratos integrales
                $table->date('fecha_expedicion');
                $table->foreignId('origen_id')->constrained('municipios');
                $table->foreignId('destino_id')->constrained('municipios');
                $table->foreignId('vehiculo_id')->constrained('vehiculos');
                $table->foreignId('remolque_id')->nullable()->constrained('remolques');
                $table->foreignId('conductor_id')->constrained('conductores');
                $table->foreignId('titular_propietario_id')->nullable()->constrained('propietarios');
                $table->foreignId('titular_tercero_id')->nullable()->constrained('terceros_carga');
                $table->unsignedBigInteger('valor_flete')->default(0); // valor pactado del viaje (COP)
                $table->unsignedBigInteger('valor_minimo_sicetac')->nullable(); // consultado en el SICE-TAC
                $table->decimal('retencion_fuente_pct', 5, 2)->default(1);
                $table->decimal('reteica_por_mil', 5, 2)->default(0); // tarifa ICA por cada 1.000
                $table->unsignedBigInteger('anticipo')->default(0);
                $table->foreignId('municipio_pago_id')->nullable()->constrained('municipios');
                $table->date('fecha_pago_saldo')->nullable();
                $table->string('responsable_cargue', 1)->default('E'); // D, R, E, C
                $table->string('responsable_descargue', 1)->default('E');
                $table->string('observaciones', 200)->nullable();
                $table->string('codigo_verificacion', 24)->unique();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('anulado_at')->nullable();
                $table->string('motivo_anulacion')->nullable();
                $table->timestamps();

                $table->unique(['empresa_id', 'numero']);
                $table->index(['empresa_id', 'fecha_expedicion']);
            });
        }

        if (! Schema::hasTable('remesas_carga')) {
            Schema::create('remesas_carga', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->unsignedInteger('numero');
                $table->date('fecha');
                $table->string('operacion', 1)->default('G'); // G general, P paqueteo, C contenedor cargado, V contenedor vacío
                $table->string('empaque_codigo', 2);
                $table->unsignedTinyInteger('naturaleza')->default(1); // 1 normal … 7 refrigerada
                $table->string('producto_codigo', 6);
                $table->string('producto_descripcion', 60);
                $table->unsignedInteger('cantidad')->nullable();
                $table->unsignedTinyInteger('unidad')->default(1); // 1 kg, 2 galones
                $table->unsignedInteger('peso_contenedor_vacio')->nullable();
                $table->foreignId('remitente_id')->constrained('terceros_carga');
                $table->foreignId('destinatario_id')->constrained('terceros_carga');
                $table->string('dueno_poliza', 1)->default('N'); // E, G, R, D, N
                $table->string('numero_poliza', 20)->nullable();
                $table->date('vence_poliza')->nullable();
                $table->string('aseguradora_nit', 11)->nullable();
                $table->unsignedSmallInteger('horas_pacto_cargue')->default(12);
                $table->unsignedTinyInteger('minutos_pacto_cargue')->default(0);
                $table->unsignedSmallInteger('horas_pacto_descargue')->default(12);
                $table->unsignedTinyInteger('minutos_pacto_descargue')->default(0);
                $table->dateTime('cita_cargue')->nullable();
                $table->dateTime('cita_descargue')->nullable();
                $table->dateTime('llegada_cargue')->nullable();
                $table->dateTime('entrada_cargue')->nullable();
                $table->dateTime('salida_cargue')->nullable();
                $table->string('observaciones', 200)->nullable();
                $table->foreignId('manifiesto_id')->nullable()->constrained('manifiestos_carga')->nullOnDelete();
                $table->string('codigo_verificacion', 24)->unique();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('anulado_at')->nullable();
                $table->string('motivo_anulacion')->nullable();
                $table->timestamps();

                $table->unique(['empresa_id', 'numero']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('remesas_carga');
        Schema::dropIfExists('manifiestos_carga');
    }
};
