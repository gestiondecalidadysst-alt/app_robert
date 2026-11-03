<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Errores inesperados de la plataforma (500), agrupados por huella (tipo + archivo + línea + mensaje)
 * para contar ocurrencias y alertar una sola vez por hora por cada error.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('errores_sistema')) {
            return;
        }

        Schema::create('errores_sistema', function (Blueprint $table) {
            $table->id();
            $table->string('huella', 64)->unique();
            $table->string('referencia', 20); // la última que vio un usuario (REF-XXXXXX)
            $table->string('clase');
            $table->text('mensaje');
            $table->string('archivo')->nullable();
            $table->unsignedInteger('linea')->nullable();
            $table->string('metodo', 10)->nullable();
            $table->string('url', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->text('traza')->nullable();
            $table->unsignedInteger('ocurrencias')->default(1);
            $table->timestamp('primera_vez');
            $table->timestamp('ultima_vez');
            $table->timestamp('alertado_at')->nullable();
            $table->timestamp('resuelto_at')->nullable();
            $table->timestamps();

            $table->index('ultima_vez');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('errores_sistema');
    }
};
