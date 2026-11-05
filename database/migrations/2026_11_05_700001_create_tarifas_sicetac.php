<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Costos mínimos del SICE-TAC por ruta y configuración vehicular.
 *
 * El Ministerio no publica un servicio de consulta: el valor se toma de las tablas
 * que publica y el RNDC lo valida al radicar. Tenerlas aquí evita que el flete
 * mínimo dependa de un número escrito a mano en cada manifiesto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarifas_sicetac', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('origen_id')->constrained('municipios')->cascadeOnDelete();
            $table->foreignId('destino_id')->constrained('municipios')->cascadeOnDelete();
            // Configuración del RNDC (50, 54, 3S3…); null = aplica a cualquiera.
            $table->unsignedSmallInteger('configuracion')->nullable();
            $table->unsignedBigInteger('valor_minimo');
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'origen_id', 'destino_id', 'configuracion', 'vigente_desde'], 'tarifas_sicetac_ruta_unica');
            $table->index(['empresa_id', 'origen_id', 'destino_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarifas_sicetac');
    }
};
