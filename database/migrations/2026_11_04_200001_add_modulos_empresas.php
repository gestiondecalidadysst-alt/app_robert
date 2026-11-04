<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulos contratados por empresa. Las empresas que ya existen quedan con todo lo construido
 * (pasajeros, seguridad vial, mantenimiento y gerencia), así que nada cambia para ellas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('empresas', 'modulos')) {
            Schema::table('empresas', function (Blueprint $table) {
                $table->json('modulos')->nullable();
            });
        }
        DB::table('empresas')->whereNull('modulos')->update(['modulos' => json_encode(['pasajeros', 'seguridad_vial', 'mantenimiento', 'gerencia'])]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('empresas', 'modulos')) {
            Schema::table('empresas', fn (Blueprint $table) => $table->dropColumn('modulos'));
        }
    }
};
