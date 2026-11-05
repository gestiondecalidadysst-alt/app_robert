<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tiempos logísticos de cada remesa: llegada, entrada y salida en el punto de cargue
 * y en el de descargue. El Ministerio los exige en tiempo real, no al cerrar el viaje,
 * y los tiempos de espera son uno de los motivos por los que la Supertransporte sanciona.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiempos_logisticos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('remesa_id')->constrained('remesas_carga')->cascadeOnDelete();
            $table->string('punto', 10);   // cargue | descargue
            $table->string('evento', 10);  // llegada | entrada | salida
            $table->timestamp('ocurrido_at');
            // Ubicación desde el celular del conductor; opcional, el navegador puede negarla.
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->foreignId('conductor_id')->nullable()->constrained('conductores')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('observacion', 200)->nullable();
            $table->timestamps();

            // Cada evento ocurre una sola vez por punto.
            $table->unique(['remesa_id', 'punto', 'evento']);
            $table->index(['empresa_id', 'ocurrido_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiempos_logisticos');
    }
};
