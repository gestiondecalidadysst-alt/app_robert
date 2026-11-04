<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo de carga, fase 1: datos maestros que el RNDC exige antes de expedir (Manual RNDC
 * Web-Service v2.3, procesos 11 Terceros y 12 Vehículos).
 * - terceros_carga: remitentes, destinatarios, titulares y otros (propietarios y conductores se
 *   reutilizan de flota y conductores).
 * - remolques: semirremolques, remolques y remolques balanceados (placa R + 5 números).
 * - vehiculos: datos de carga con los códigos del RNDC.
 * - registros_rndc: bitácora de cada envío (o registro manual) al RNDC con su número de radicado.
 * - empresas: credenciales del RNDC (cifradas), conexión, rol y modalidad de cobro.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('terceros_carga')) {
            Schema::create('terceros_carga', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('tipo_identificacion_id')->constrained('tipos_identificacion');
                $table->string('documento', 15);
                $table->string('nombre', 100); // nombres o razón social
                $table->string('primer_apellido', 100)->nullable();
                $table->string('segundo_apellido', 100)->nullable();
                $table->string('sede_codigo', 6)->default('1');
                $table->string('sede_nombre', 20)->default('PRINCIPAL');
                $table->string('telefono', 7)->nullable(); // fijo
                $table->string('celular', 10)->nullable();
                $table->string('email')->nullable();
                $table->string('direccion', 60);
                $table->foreignId('municipio_id')->constrained('municipios');
                $table->json('roles')->nullable(); // remitente, destinatario, titular, otros
                $table->boolean('activo')->default(true);
                $table->text('observaciones')->nullable();
                $table->timestamps();

                $table->unique(['empresa_id', 'tipo_identificacion_id', 'documento', 'sede_codigo'], 'terceros_carga_unico');
            });
        }

        if (! Schema::hasTable('remolques')) {
            Schema::create('remolques', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->string('placa', 6);
                $table->unsignedTinyInteger('configuracion'); // código RNDC (61-64, 71-74, 81-85)
                $table->unsignedTinyInteger('numero_ejes')->nullable();
                $table->string('marca_codigo', 10); // código RNDC de la marca de semirremolque y remolque
                $table->string('marca_nombre', 60)->nullable();
                $table->unsignedSmallInteger('modelo');
                $table->unsignedInteger('peso_vacio'); // kg
                $table->unsignedInteger('capacidad')->nullable();
                $table->unsignedTinyInteger('unidad_capacidad')->default(1); // 1 kg, 2 galones
                $table->string('carroceria_codigo', 3)->nullable();
                $table->foreignId('propietario_id')->constrained('propietarios');
                $table->foreignId('tenedor_id')->nullable()->constrained('propietarios');
                $table->boolean('activo')->default(true);
                $table->text('observaciones')->nullable();
                $table->timestamps();

                $table->unique(['empresa_id', 'placa']);
            });
        }

        if (! Schema::hasColumn('vehiculos', 'rndc_configuracion')) {
            Schema::table('vehiculos', function (Blueprint $table) {
                $table->unsignedTinyInteger('rndc_configuracion')->nullable(); // 50-57 rígidos y tractocamiones
                $table->unsignedTinyInteger('numero_ejes')->nullable();
                $table->string('rndc_marca', 10)->nullable();
                $table->string('rndc_linea', 10)->nullable();
                $table->string('rndc_color', 5)->nullable();
                $table->string('rndc_carroceria', 3)->nullable();
                $table->unsignedTinyInteger('rndc_combustible')->nullable(); // 1 ACPM, 2 gasolina, 3 gas, 4 gas/gasolina
                $table->unsignedInteger('peso_vacio')->nullable(); // kg
                $table->unsignedTinyInteger('unidad_capacidad')->default(1);
                $table->unsignedSmallInteger('ano_repotenciacion')->nullable();
                $table->string('soat_nit_aseguradora', 11)->nullable();
                $table->foreignId('tenedor_id')->nullable()->constrained('propietarios')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('registros_rndc')) {
            Schema::create('registros_rndc', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->morphs('registrable');
                $table->unsignedSmallInteger('proceso'); // 11 terceros, 12 vehículos…
                $table->string('estado', 12); // registrado | error | manual
                $table->string('ingresoid', 30)->nullable(); // número de radicado del RNDC
                $table->string('ambiente', 12)->nullable(); // pruebas | produccion | manual
                $table->json('variables')->nullable(); // lo enviado (sin credenciales)
                $table->text('respuesta')->nullable();
                $table->json('errores')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedInteger('duracion_ms')->nullable();
                $table->timestamps();

                $table->index(['empresa_id', 'created_at']);
            });
        }

        if (! Schema::hasColumn('empresas', 'rndc_usuario')) {
            Schema::table('empresas', function (Blueprint $table) {
                $table->text('rndc_usuario')->nullable(); // cifrado
                $table->text('rndc_clave')->nullable(); // cifrada
                $table->string('rndc_ambiente', 12)->default('pruebas');
                $table->boolean('rndc_conectado')->default(false);
                $table->string('carga_rol', 15)->default('transportadora'); // transportadora | generador | ambos
                $table->string('carga_cobro_modo', 15)->nullable(); // fijo | por_manifiesto
                $table->decimal('carga_cobro_valor', 12, 2)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('registros_rndc');
        if (Schema::hasColumn('vehiculos', 'rndc_configuracion')) {
            Schema::table('vehiculos', function (Blueprint $table) {
                $table->dropConstrainedForeignId('tenedor_id');
                $table->dropColumn(['rndc_configuracion', 'numero_ejes', 'rndc_marca', 'rndc_linea', 'rndc_color', 'rndc_carroceria',
                    'rndc_combustible', 'peso_vacio', 'unidad_capacidad', 'ano_repotenciacion', 'soat_nit_aseguradora']);
            });
        }
        Schema::dropIfExists('remolques');
        Schema::dropIfExists('terceros_carga');
        if (Schema::hasColumn('empresas', 'rndc_usuario')) {
            Schema::table('empresas', fn (Blueprint $table) => $table->dropColumn([
                'rndc_usuario', 'rndc_clave', 'rndc_ambiente', 'rndc_conectado', 'carga_rol', 'carga_cobro_modo', 'carga_cobro_valor',
            ]));
        }
    }
};
