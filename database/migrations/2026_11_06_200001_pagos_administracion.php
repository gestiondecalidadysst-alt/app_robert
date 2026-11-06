<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pagos de administración: la cuota mensual que el vehículo afiliado paga a la empresa.
 *
 * El valor sale de ValorAdministracion (por clase, ya existía) y se puede ajustar por vehículo.
 * Cada mes se genera una cuenta de cobro (cuota) por vehículo activo; los pagos se aplican a las
 * cuotas más viejas primero y generan un recibo. El estado de cuenta es la suma de lo pendiente.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Ajuste de la cuota para un vehículo puntual; null = usa el valor de su clase.
        Schema::table('vehiculos', function (Blueprint $table): void {
            $table->unsignedBigInteger('administracion_valor')->nullable()->after('estado');
        });

        Schema::create('cuotas_administracion', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('vehiculo_id')->constrained('vehiculos')->cascadeOnDelete();
            $table->foreignId('propietario_id')->nullable()->constrained('propietarios')->nullOnDelete();
            $table->foreignId('clase_vehiculo_id')->nullable()->constrained('clases_vehiculo')->nullOnDelete();
            $table->date('periodo');            // primer día del mes que cobra
            $table->unsignedBigInteger('valor'); // cuota del mes
            $table->unsignedBigInteger('pagado')->default(0);
            $table->timestamp('anulado_at')->nullable();
            $table->string('motivo_anulacion')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'vehiculo_id', 'periodo']);
            // Para el estado de cuenta: lo pendiente por empresa y vehículo.
            $table->index(['empresa_id', 'vehiculo_id', 'anulado_at']);
            $table->index(['empresa_id', 'periodo']);
        });

        Schema::create('pagos_administracion', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('vehiculo_id')->constrained('vehiculos')->cascadeOnDelete();
            $table->unsignedInteger('numero');  // consecutivo del recibo por empresa
            $table->date('fecha');
            $table->unsignedBigInteger('valor');
            $table->string('metodo')->nullable();
            $table->string('referencia')->nullable();
            $table->string('observacion')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anulado_at')->nullable();
            $table->string('motivo_anulacion')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['empresa_id', 'numero']);
            $table->index(['empresa_id', 'vehiculo_id']);
        });

        // Cómo se repartió cada pago entre las cuotas (para revertir bien al anular).
        Schema::create('cuota_administracion_pago', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cuota_administracion_id')->constrained('cuotas_administracion')->cascadeOnDelete();
            $table->foreignId('pago_administracion_id')->constrained('pagos_administracion')->cascadeOnDelete();
            $table->unsignedBigInteger('valor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuota_administracion_pago');
        Schema::dropIfExists('pagos_administracion');
        Schema::dropIfExists('cuotas_administracion');
        Schema::table('vehiculos', function (Blueprint $table): void {
            $table->dropColumn('administracion_valor');
        });
    }
};
