<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos que exige el formato oficial del manifiesto electrónico de carga (Ministerio de Transporte):
 * municipio intermedio, trayectos en vacío, vía a utilizar, segundo conductor y segundo remolque,
 * peso vacío del remolque, nombre de la aseguradora del SOAT y retención FOPAT.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('manifiestos_carga', 'municipio_intermedio_id')) {
            Schema::table('manifiestos_carga', function (Blueprint $table) {
                $table->foreignId('municipio_intermedio_id')->nullable()->after('destino_id')->constrained('municipios')->nullOnDelete();
                $table->json('trayectos_vacio')->nullable()->after('municipio_intermedio_id'); // [{origen_id, destino_id}, ...] máx 2
                $table->string('via_utilizar', 300)->nullable()->after('trayectos_vacio');
                $table->foreignId('remolque_2_id')->nullable()->after('remolque_id')->constrained('remolques')->nullOnDelete();
                $table->foreignId('conductor_2_id')->nullable()->after('conductor_id')->constrained('conductores')->nullOnDelete();
                $table->decimal('retencion_fopat_pct', 5, 2)->default(0)->after('reteica_por_mil'); // por 1000
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('manifiestos_carga', 'municipio_intermedio_id')) {
            Schema::table('manifiestos_carga', function (Blueprint $table) {
                $table->dropConstrainedForeignId('municipio_intermedio_id');
                $table->dropConstrainedForeignId('remolque_2_id');
                $table->dropConstrainedForeignId('conductor_2_id');
                $table->dropColumn(['trayectos_vacio', 'via_utilizar', 'retencion_fopat_pct']);
            });
        }
    }
};
