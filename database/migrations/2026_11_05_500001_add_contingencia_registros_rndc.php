<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contingencia del RNDC: cuando el servicio del Ministerio está caído, el envío queda
 * en espera y se reintenta solo. Antes solo había «registrado | error | manual», y una
 * caída se guardaba como error, indistinguible de un rechazo de datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registros_rndc', function (Blueprint $table): void {
            $table->unsignedTinyInteger('intentos')->default(0)->after('duracion_ms');
            $table->timestamp('reintentar_a')->nullable()->after('intentos');
            $table->index(['estado', 'reintentar_a']);
        });
    }

    public function down(): void
    {
        Schema::table('registros_rndc', function (Blueprint $table): void {
            $table->dropIndex(['estado', 'reintentar_a']);
            $table->dropColumn(['intentos', 'reintentar_a']);
        });
    }
};
