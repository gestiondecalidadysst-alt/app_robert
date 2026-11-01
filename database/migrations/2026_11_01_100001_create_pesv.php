<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo 5 – PESV y portal del conductor:
 * firma única del conductor, ítems de chequeo configurables por empresa e inspecciones
 * (preoperacional diario e inspección mensual). Los usuarios de conductores ingresan con
 * su documento, por eso el correo deja de ser obligatorio.
 *
 * Idempotente: se puede volver a correr sobre un despliegue que falló a medias.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });

        if (! Schema::hasColumn('users', 'debe_cambiar_clave')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('debe_cambiar_clave')->default(false)->after('activo');
            });
        }

        if (! Schema::hasColumn('conductores', 'firma_path')) {
            Schema::table('conductores', function (Blueprint $table) {
                $table->string('firma_path')->nullable()->after('foto_path'); // disco privado
                $table->timestamp('firma_actualizada_at')->nullable()->after('firma_path');
            });
        }

        if (! Schema::hasTable('items_chequeo')) {
            Schema::create('items_chequeo', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->string('tipo', 20); // preoperacional | mensual
                $table->string('grupo', 60);
                $table->string('descripcion');
                $table->boolean('critico')->default(false); // si falla, el vehículo no queda apto
                $table->unsignedSmallInteger('orden')->default(0);
                $table->boolean('activo')->default(true);
                $table->timestamps();

                $table->index(['empresa_id', 'tipo', 'activo']);
            });
        }

        if (! Schema::hasTable('inspecciones')) {
            Schema::create('inspecciones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->string('tipo', 20); // preoperacional | mensual
                $table->foreignId('vehiculo_id')->constrained('vehiculos');
                $table->foreignId('conductor_id')->nullable()->constrained('conductores')->nullOnDelete();
                $table->foreignId('fuec_id')->nullable()->constrained('fuecs')->nullOnDelete();
                $table->date('fecha');
                $table->unsignedInteger('kilometraje')->nullable();
                $table->string('resultado', 10); // apto | no_apto
                $table->json('respuestas'); // copia de cada ítem con su respuesta
                $table->json('datos'); // copia de vehículo, conductor y empresa al registrar
                $table->text('observaciones')->nullable();
                $table->string('firma_path')->nullable(); // copia de la firma del conductor vigente
                $table->string('codigo_verificacion', 32)->unique();
                $table->string('ip', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->decimal('latitud', 10, 7)->nullable();
                $table->decimal('longitud', 10, 7)->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // quién la registró
                $table->timestamps();

                $table->index(['empresa_id', 'tipo', 'fecha']);
                $table->index(['vehiculo_id', 'tipo', 'fecha']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inspecciones');
        Schema::dropIfExists('items_chequeo');

        Schema::table('conductores', function (Blueprint $table) {
            $table->dropColumn(array_filter(['firma_path', 'firma_actualizada_at'], fn (string $c) => Schema::hasColumn('conductores', $c)));
        });

        if (Schema::hasColumn('users', 'debe_cambiar_clave')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('debe_cambiar_clave'));
        }
    }
};
