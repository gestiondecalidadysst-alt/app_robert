<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cuota de administración por clase de vehículo, con historial.
        Schema::create('valores_administracion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('clase_vehiculo_id')->constrained('clases_vehiculo');
            $table->decimal('valor', 14, 2);
            $table->date('vigente_desde');
            $table->timestamps();

            $table->unique(['empresa_id', 'clase_vehiculo_id', 'vigente_desde'], 'valores_admin_unico');
        });

        // Empresas con las que se tiene convenio o vinculación de flota.
        Schema::create('empresas_convenio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nit', 15);
            $table->unsignedTinyInteger('dv')->nullable();
            $table->string('razon_social');
            $table->string('tipo_vinculacion', 30); // convenio_empresarial | vinculacion_flota
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'nit']);
        });

        Schema::table('activity_log', function (Blueprint $table) {
            $table->foreignId('empresa_id')->nullable()->after('id')->constrained('empresas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropConstrainedForeignId('empresa_id');
        });
        Schema::dropIfExists('empresas_convenio');
        Schema::dropIfExists('valores_administracion');
    }
};
