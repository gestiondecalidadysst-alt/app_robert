<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Listado de pasajeros por FUEC (exclusivo para Turismo y Grupo específico).
 * El estado del listado vive en el propio FUEC; los pasajeros se autorregistran
 * por un enlace público y el operador cierra el listado cuando está completo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuecs', function (Blueprint $table): void {
            // null = el FUEC no lleva listado; 'abierto' | 'cerrado' cuando aplica.
            $table->string('pasajeros_estado', 10)->nullable()->after('recorrido');
            // El enlace de autorregistro se comparte ampliamente: el token va en claro.
            $table->string('pasajeros_token', 32)->nullable()->unique()->after('pasajeros_estado');
            $table->string('pasajeros_codigo', 32)->nullable()->unique()->after('pasajeros_token');
            $table->timestamp('pasajeros_cerrado_at')->nullable()->after('pasajeros_codigo');
            $table->foreignId('pasajeros_cerrado_por')->nullable()->after('pasajeros_cerrado_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('pasajeros_enviado_at')->nullable()->after('pasajeros_cerrado_por');
        });

        Schema::create('pasajeros_fuec', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('fuec_id')->constrained('fuecs')->cascadeOnDelete();
            $table->unsignedInteger('orden')->default(0);
            $table->string('nombre');
            $table->string('tipo_documento', 8)->default('CC'); // CC, TI, CE, PAS, RC
            $table->string('documento', 30);
            $table->string('telefono', 30)->nullable();
            $table->boolean('es_menor')->default(false);
            $table->string('acudiente_nombre')->nullable();
            $table->string('acudiente_documento', 30)->nullable();
            $table->timestamp('registrado_at')->nullable();
            $table->timestamps();

            $table->unique(['fuec_id', 'documento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pasajeros_fuec');
        Schema::table('fuecs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pasajeros_cerrado_por');
            $table->dropColumn([
                'pasajeros_estado', 'pasajeros_token', 'pasajeros_codigo',
                'pasajeros_cerrado_at', 'pasajeros_enviado_at',
            ]);
        });
    }
};
