<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fecha real (date) en cada línea de la cotización para poder elegirla con calendario.
 * `fecha_texto` se mantiene como alternativa libre (rangos: "del 6 al 11", "ida y regreso").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizacion_items', function (Blueprint $table): void {
            $table->date('fecha')->nullable()->after('cotizacion_id');
        });
    }

    public function down(): void
    {
        Schema::table('cotizacion_items', function (Blueprint $table): void {
            $table->dropColumn('fecha');
        });
    }
};
