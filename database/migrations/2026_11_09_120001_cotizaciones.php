<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cotizador de servicios (transporte especial). La cotización se dirige a un cliente o prospecto,
 * lleva líneas Fecha/Ruta/Duración/Valor, descuento e IVA opcional, y genera un PDF con la marca
 * de la empresa para enviar al cliente. Al aceptarse se puede convertir en contrato.
 *
 * El diseño del PDF (colores, bloques "incluye" y "equipo", validez, pie, logos de certificación)
 * se configura por empresa.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Configuración del diseño de la cotización por empresa.
        Schema::table('empresas', function (Blueprint $table): void {
            $table->string('cotizacion_color', 9)->nullable()->after('pie_documentos_path');
            $table->string('cotizacion_color_acento', 9)->nullable()->after('cotizacion_color');
            $table->unsignedSmallInteger('cotizacion_validez_dias')->nullable()->after('cotizacion_color_acento');
            $table->json('cotizacion_incluye')->nullable()->after('cotizacion_validez_dias');
            $table->json('cotizacion_equipo')->nullable()->after('cotizacion_incluye');
            $table->string('cotizacion_telefono_2')->nullable()->after('cotizacion_equipo');
            $table->string('cotizacion_instagram')->nullable()->after('cotizacion_telefono_2');
            $table->json('cotizacion_certificados')->nullable()->after('cotizacion_instagram');
        });

        Schema::create('cotizaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->unsignedInteger('numero');                 // consecutivo por empresa
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            // Prospecto: destinatario que aún no es cliente registrado.
            $table->string('prospecto_nombre')->nullable();
            $table->string('prospecto_contacto')->nullable();
            $table->string('prospecto_telefono')->nullable();
            $table->string('prospecto_email')->nullable();
            $table->string('ciudad')->nullable();
            $table->date('fecha');
            $table->date('valido_hasta');
            $table->string('asunto', 500)->nullable();
            $table->unsignedBigInteger('descuento')->default(0);
            $table->boolean('aplica_iva')->default(false);
            $table->unsignedSmallInteger('iva_porcentaje')->default(19);
            $table->string('estado')->default('borrador');     // borrador/enviada/aceptada/rechazada
            $table->text('observaciones')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('contrato_id')->nullable()->constrained('contratos')->nullOnDelete();
            $table->timestamp('enviada_at')->nullable();
            $table->timestamp('anulado_at')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'numero']);
            $table->index(['empresa_id', 'estado']);
        });

        Schema::create('cotizacion_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cotizacion_id')->constrained('cotizaciones')->cascadeOnDelete();
            $table->string('fecha_texto')->nullable();  // "6 de octubre"
            $table->string('ruta');                      // "Aeropuerto Los Garzones – Hotel GHL"
            $table->string('detalle')->nullable();       // duración/detalle: "6 personas · 2 llegadas"
            $table->unsignedBigInteger('valor')->default(0);
            $table->unsignedInteger('orden')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizacion_items');
        Schema::dropIfExists('cotizaciones');
        Schema::table('empresas', function (Blueprint $table): void {
            $table->dropColumn([
                'cotizacion_color', 'cotizacion_color_acento', 'cotizacion_validez_dias',
                'cotizacion_incluye', 'cotizacion_equipo', 'cotizacion_telefono_2',
                'cotizacion_instagram', 'cotizacion_certificados',
            ]);
        });
    }
};
