<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los planes de acción se numeraban con el id de la base, que es común a toda la plataforma: el
 * primer plan de una empresa nueva salía como PA-00005 y los números le saltaban cuando otra
 * empresa creaba registros. Además dejaba ver cuántos planes existen en la plataforma.
 *
 * Pasan a numerarse por empresa, como contratos, cotizaciones, remesas, manifiestos y pagos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planes_accion', function (Blueprint $table) {
            $table->unsignedInteger('numero')->nullable()->after('empresa_id');
        });

        // Renumera lo que ya existe: por empresa, en el mismo orden en que se crearon.
        foreach (DB::table('planes_accion')->distinct()->pluck('empresa_id') as $empresa) {
            $n = 0;

            foreach (DB::table('planes_accion')->where('empresa_id', $empresa)->orderBy('id')->pluck('id') as $id) {
                DB::table('planes_accion')->where('id', $id)->update(['numero' => ++$n]);
            }
        }

        Schema::table('planes_accion', function (Blueprint $table) {
            $table->unsignedInteger('numero')->nullable(false)->change();
            $table->unique(['empresa_id', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::table('planes_accion', function (Blueprint $table) {
            $table->dropUnique(['empresa_id', 'numero']);
            $table->dropColumn('numero');
        });
    }
};
