<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oficinas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nombre');
            $table->foreignId('municipio_id')->nullable()->constrained('municipios');
            $table->string('direccion')->nullable();
            $table->string('telefono', 30)->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'nombre']);
        });

        Schema::create('empresa_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('oficina_id')->nullable()->constrained('oficinas')->nullOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_user');
        Schema::dropIfExists('oficinas');
    }
};
