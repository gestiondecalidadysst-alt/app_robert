<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->foreignId('tipo_identificacion_id')->nullable()->constrained('tipos_identificacion');
            $table->string('nit', 15)->unique();
            $table->unsignedTinyInteger('dv')->nullable();
            $table->string('razon_social');
            $table->string('nombre_comercial')->nullable();
            $table->foreignId('municipio_id')->nullable()->constrained('municipios');
            $table->string('direccion')->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('representante_legal')->nullable();
            $table->string('representante_documento', 20)->nullable();
            $table->string('resolucion_habilitacion')->nullable();
            $table->unsignedSmallInteger('anio_habilitacion')->nullable();
            $table->string('codigo_territorial', 10)->nullable();
            $table->string('logo_path')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
