<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Código de verificación de la constancia de cada consulta de tránsito (QR y descarga). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('consultas_transito', 'codigo_verificacion')) {
            Schema::table('consultas_transito', function (Blueprint $table) {
                $table->string('codigo_verificacion', 24)->nullable()->unique();
            });
        }
        DB::table('consultas_transito')->whereNull('codigo_verificacion')->orderBy('id')->pluck('id')
            ->each(fn ($id) => DB::table('consultas_transito')->where('id', $id)->update(['codigo_verificacion' => Str::lower(Str::random(24))]));
    }

    public function down(): void
    {
        if (Schema::hasColumn('consultas_transito', 'codigo_verificacion')) {
            Schema::table('consultas_transito', function (Blueprint $table) {
                $table->dropUnique(['codigo_verificacion']);
                $table->dropColumn('codigo_verificacion');
            });
        }
    }
};
