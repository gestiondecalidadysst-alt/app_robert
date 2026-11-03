<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Recibos de caja de los pagos de contratos:
 * - Cada pago recibe un número consecutivo por empresa (prefijo + número), un código para el QR de
 *   verificación y la foto del saldo antes y después del pago.
 * - Los pagos ya no se borran: se anulan con motivo (el recibo conserva su número y sale ANULADO).
 * - La empresa configura el prefijo y el número con el que arranca (para continuar su talonario).
 *
 * Idempotente: los pagos existentes reciben número, código y saldos en orden de fecha.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('empresas', 'recibo_prefijo')) {
            Schema::table('empresas', function (Blueprint $table) {
                $table->string('recibo_prefijo', 10)->default('RC');
                $table->unsignedInteger('recibo_numero_inicial')->default(1);
            });
        }

        if (! Schema::hasColumn('pagos_contrato', 'numero')) {
            Schema::table('pagos_contrato', function (Blueprint $table) {
                $table->string('prefijo', 10)->nullable()->after('contrato_id');
                $table->unsignedInteger('numero')->nullable()->after('prefijo');
                $table->string('codigo_verificacion', 32)->nullable()->unique()->after('numero');
                $table->decimal('saldo_anterior', 14, 2)->nullable()->after('valor');
                $table->decimal('saldo_despues', 14, 2)->nullable()->after('saldo_anterior');
                $table->timestamp('anulado_at')->nullable();
                $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
                $table->text('motivo_anulacion')->nullable();

                $table->unique(['empresa_id', 'numero'], 'pagos_contrato_empresa_numero_unique');
            });
        }

        $this->numerarExistentes();
    }

    private function numerarExistentes(): void
    {
        $saldos = [];
        $consecutivos = [];

        DB::table('pagos_contrato')->whereNull('numero')->orderBy('fecha')->orderBy('id')->get()
            ->each(function ($pago) use (&$saldos, &$consecutivos): void {
                $consecutivos[$pago->empresa_id] ??= (int) DB::table('pagos_contrato')->where('empresa_id', $pago->empresa_id)->max('numero');
                $consecutivos[$pago->empresa_id]++;

                $saldos[$pago->contrato_id] ??= (float) DB::table('contratos')->where('id', $pago->contrato_id)->value('valor');
                $anterior = $saldos[$pago->contrato_id];
                $saldos[$pago->contrato_id] = max(0, round($anterior - (float) $pago->valor, 2));

                DB::table('pagos_contrato')->where('id', $pago->id)->update([
                    'prefijo' => 'RC',
                    'numero' => $consecutivos[$pago->empresa_id],
                    'codigo_verificacion' => Str::lower(Str::random(24)),
                    'saldo_anterior' => $anterior,
                    'saldo_despues' => $saldos[$pago->contrato_id],
                ]);
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('pagos_contrato', 'numero')) {
            Schema::table('pagos_contrato', function (Blueprint $table) {
                $table->dropUnique('pagos_contrato_empresa_numero_unique');
                $table->dropUnique(['codigo_verificacion']);
                $table->dropConstrainedForeignId('anulado_por');
                $table->dropColumn(['prefijo', 'numero', 'codigo_verificacion', 'saldo_anterior', 'saldo_despues', 'anulado_at', 'motivo_anulacion']);
            });
        }

        if (Schema::hasColumn('empresas', 'recibo_prefijo')) {
            Schema::table('empresas', function (Blueprint $table) {
                $table->dropColumn(['recibo_prefijo', 'recibo_numero_inicial']);
            });
        }
    }
};
