<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices para las consultas que más se repiten.
 *
 * Tres huecos detectados al revisar la base:
 *
 * 1. `remesas_carga` no tenía ningún índice por fecha, y es la tabla que más crece
 *    del módulo de carga; el tablero la consulta por rango de fechas.
 * 2. Nada indexaba `anulado_at`, que entra en casi todas las consultas del módulo
 *    («solo lo que no está anulado»).
 * 3. El filtro «remesas sin manifiesto» recorría la tabla entera: el índice de la
 *    clave foránea solo sirve para buscar un manifiesto concreto.
 *
 * `empresa_id` va siempre primero porque el filtro por empresa entra en TODA consulta
 * de la aplicación. `anulado_at` justo después, y la columna de rango al final: MySQL
 * usa un rango solo en la última columna del índice. No hace falta añadir `id` para
 * ordenar: en InnoDB la clave primaria viaja implícita al final de cada índice
 * secundario, así que `order by id desc` sale gratis sobre lo ya filtrado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('remesas_carga', function (Blueprint $table): void {
            $table->index(['empresa_id', 'anulado_at', 'fecha'], 'remesas_carga_empresa_anulado_fecha_index');
            // Para «remesas sin manifiesto», del tablero de Inicio y del selector del manifiesto.
            $table->index(['empresa_id', 'manifiesto_id'], 'remesas_carga_empresa_manifiesto_index');
        });

        Schema::table('manifiestos_carga', function (Blueprint $table): void {
            $table->index(['empresa_id', 'anulado_at', 'fecha_expedicion'], 'manifiestos_carga_empresa_anulado_fecha_index');
        });

        // Fuera del módulo de carga, las dos tablas que más se consultan así.
        Schema::table('contratos', function (Blueprint $table): void {
            $table->index(['empresa_id', 'anulado_at'], 'contratos_empresa_anulado_index');
        });

        Schema::table('fuecs', function (Blueprint $table): void {
            $table->index(['empresa_id', 'anulado_at'], 'fuecs_empresa_anulado_index');
        });

        Schema::table('pagos_contrato', function (Blueprint $table): void {
            $table->index(['empresa_id', 'anulado_at'], 'pagos_contrato_empresa_anulado_index');
        });
    }

    public function down(): void
    {
        Schema::table('remesas_carga', function (Blueprint $table): void {
            $table->dropIndex('remesas_carga_empresa_anulado_fecha_index');
            $table->dropIndex('remesas_carga_empresa_manifiesto_index');
        });

        Schema::table('manifiestos_carga', function (Blueprint $table): void {
            $table->dropIndex('manifiestos_carga_empresa_anulado_fecha_index');
        });

        Schema::table('contratos', function (Blueprint $table): void {
            $table->dropIndex('contratos_empresa_anulado_index');
        });

        Schema::table('fuecs', function (Blueprint $table): void {
            $table->dropIndex('fuecs_empresa_anulado_index');
        });

        Schema::table('pagos_contrato', function (Blueprint $table): void {
            $table->dropIndex('pagos_contrato_empresa_anulado_index');
        });
    }
};
