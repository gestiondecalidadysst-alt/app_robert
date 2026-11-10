<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de cada vez que ocurre un error del sistema. El error se sigue agrupando por huella
 * (una fila en errores_sistema), pero aquí queda el rastro de cada ocurrencia: su referencia,
 * dónde pasó, quién la vio y cuándo. Permite diagnosticar a fondo y encontrar el error por
 * cualquier REF que reporte un usuario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ocurrencias_error', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('error_sistema_id')->constrained('errores_sistema')->cascadeOnDelete();
            $table->string('referencia', 20)->index();
            $table->string('url', 500)->nullable();
            $table->string('metodo', 10)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->timestamp('ocurrio_at');

            $table->index(['error_sistema_id', 'ocurrio_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ocurrencias_error');
    }
};
