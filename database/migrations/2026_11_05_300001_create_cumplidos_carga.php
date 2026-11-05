<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo de carga, fase 3: cumplidos de remesa (proceso 5) y de manifiesto (proceso 6).
 * Los tiempos reales de descargue, cantidad entregada y ajustes al pago llegan desde el portal del
 * conductor o la oficina. El cumplido queda guardado y se envía al RNDC con su radicado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cumplidos_remesa')) {
            Schema::create('cumplidos_remesa', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('remesa_id')->unique()->constrained('remesas_carga')->cascadeOnDelete();
                $table->string('tipo', 1)->default('C'); // C normal, S suspensión
                $table->string('motivo_suspension', 1)->nullable(); // A accidente, V varada, S siniestro
                $table->unsignedInteger('cantidad_entregada')->nullable();
                $table->dateTime('llegada_descargue')->nullable();
                $table->dateTime('entrada_descargue')->nullable();
                $table->dateTime('salida_descargue')->nullable();
                $table->string('observaciones', 200)->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cumplidos_manifiesto')) {
            Schema::create('cumplidos_manifiesto', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('manifiesto_id')->unique()->constrained('manifiestos_carga')->cascadeOnDelete();
                $table->string('tipo', 1)->default('C'); // C normal, S suspensión
                $table->string('motivo_suspension', 2)->nullable(); // A, V, S
                $table->string('consecuencia_suspension', 1)->nullable(); // C, V, R, T, F
                $table->date('fecha_entrega_documentos');
                $table->unsignedBigInteger('valor_adicional_cargue')->default(0);
                $table->unsignedBigInteger('valor_adicional_descargue')->default(0);
                $table->unsignedBigInteger('valor_adicional_flete')->default(0);
                $table->string('motivo_adicional', 1)->nullable(); // C, R, O
                $table->unsignedBigInteger('valor_descuento_flete')->default(0);
                $table->string('motivo_descuento', 1)->nullable(); // F, D, C, V
                $table->unsignedBigInteger('valor_sobreanticipo')->default(0);
                $table->string('observaciones', 200)->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cumplidos_manifiesto');
        Schema::dropIfExists('cumplidos_remesa');
    }
};
