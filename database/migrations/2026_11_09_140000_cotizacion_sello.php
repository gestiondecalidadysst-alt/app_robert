<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sello "Vigilado SuperTransporte" (escudo oficial) que la empresa sube una vez
 * y se muestra en el encabezado de la cotización.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            $table->string('cotizacion_sello_path')->nullable()->after('cotizacion_instagram');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            $table->dropColumn('cotizacion_sello_path');
        });
    }
};
