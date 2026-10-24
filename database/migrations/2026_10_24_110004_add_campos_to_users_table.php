<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tipo_identificacion_id')->nullable()->after('id')->constrained('tipos_identificacion');
            $table->string('documento', 20)->nullable()->after('tipo_identificacion_id');
            $table->string('telefono', 30)->nullable()->after('email');
            $table->string('avatar_path')->nullable()->after('telefono');
            $table->boolean('es_superadmin')->default(false)->after('avatar_path');
            $table->boolean('activo')->default(true)->after('es_superadmin');
            $table->foreignId('ultima_empresa_id')->nullable()->after('activo')->constrained('empresas')->nullOnDelete();
            $table->timestamp('ultimo_acceso_at')->nullable()->after('ultima_empresa_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ultima_empresa_id');
            $table->dropConstrainedForeignId('tipo_identificacion_id');
            $table->dropColumn(['documento', 'telefono', 'avatar_path', 'es_superadmin', 'activo', 'ultimo_acceso_at']);
        });
    }
};
