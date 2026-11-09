<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regla por empresa: exigir que el contrato esté firmado por el cliente
 * antes de poder expedir FUEC. El superadmin la activa/desactiva por empresa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            $table->boolean('exigir_contrato_firmado_fuec')->default(false)->after('transito_frecuencia_dias');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            $table->dropColumn('exigir_contrato_firmado_fuec');
        });
    }
};
