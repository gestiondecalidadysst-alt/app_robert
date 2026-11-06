<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suscripciones Web Push de los usuarios (p. ej. conductores con la PWA instalada). Cada navegador
 * que acepta recibir avisos deja aquí su endpoint y sus llaves. Se da de baja sola cuando el
 * navegador la cancela (el envío recibe 404/410).
 *
 * El endpoint puede ser largo, así que la unicidad se controla con su hash (endpoint_hash).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suscripciones_push', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->text('endpoint');
            $table->char('endpoint_hash', 64)->unique();
            $table->string('p256dh');
            $table->string('auth');
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suscripciones_push');
    }
};
