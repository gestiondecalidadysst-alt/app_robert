<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Franja horaria del servicio en cada FUEC (por defecto la del contrato). Sirve para no asignar
 * el mismo vehículo o conductor a dos servicios que se cruzan. Vacía = todo el día.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('fuecs', 'hora_inicio')) {
            Schema::table('fuecs', function (Blueprint $table) {
                $table->time('hora_inicio')->nullable()->after('fecha_fin');
                $table->time('hora_fin')->nullable()->after('hora_inicio');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('fuecs', 'hora_inicio')) {
            Schema::table('fuecs', fn (Blueprint $table) => $table->dropColumn(['hora_inicio', 'hora_fin']));
        }
    }
};
