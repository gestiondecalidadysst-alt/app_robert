<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogos nacionales compartidos por todas las empresas.
 * Los administra el superadministrador en el panel /admin.
 */
return new class extends Migration
{
    /** Catálogos que solo tienen nombre. */
    private const SIMPLES = [
        'marcas',
        'clases_vehiculo',
        'carrocerias',
        'combustibles',
        'tipos_servicio',
        'niveles_academicos',
    ];

    public function up(): void
    {
        Schema::create('tipos_identificacion', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 5)->unique();
            $table->string('nombre');
            $table->boolean('es_empresa')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        foreach (self::SIMPLES as $tabla) {
            Schema::create($tabla, function (Blueprint $table) {
                $table->id();
                $table->string('nombre')->unique();
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }

        Schema::create('lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marca_id')->constrained('marcas');
            $table->string('nombre');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['marca_id', 'nombre']);
        });

        Schema::create('categorias_licencia', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 5)->unique();
            $table->string('descripcion');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        foreach (['eps', 'arl', 'aseguradoras'] as $tabla) {
            Schema::create($tabla, function (Blueprint $table) {
                $table->id();
                $table->string('nombre')->unique();
                $table->string('nit', 15)->nullable();
                $table->string('telefono', 30)->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (['aseguradoras', 'arl', 'eps', 'categorias_licencia', 'lineas'] as $tabla) {
            Schema::dropIfExists($tabla);
        }
        foreach (array_reverse(self::SIMPLES) as $tabla) {
            Schema::dropIfExists($tabla);
        }
        Schema::dropIfExists('tipos_identificacion');
    }
};
