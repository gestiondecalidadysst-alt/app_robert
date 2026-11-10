<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manual de la aplicación: secciones editables desde el panel del superadministrador.
 * Es contenido global de la plataforma (no por empresa).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secciones_manual', function (Blueprint $table): void {
            $table->id();
            $table->string('grupo')->default('General');     // agrupador en el índice (p. ej. "Operación")
            $table->string('titulo');
            $table->string('slug')->unique();
            $table->unsignedInteger('orden')->default(0);
            $table->string('icono')->nullable();             // heroicon opcional
            $table->longText('contenido')->nullable();       // HTML del editor
            $table->timestamps();

            $table->index(['grupo', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secciones_manual');
    }
};
