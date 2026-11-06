<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SMS como servicio de la plataforma: una sola cuenta Onurix, que el superadministrador
 * configura y cobra a cada empresa. Antes cada empresa ponía sus propias credenciales.
 *
 * - proveedor_sms: la cuenta global (credenciales, costo por segmento, precio de venta).
 * - envios_mensaje: segmentos, precio y costo de cada SMS, para poder facturar.
 * - empresas: precio propio opcional por empresa (el límite y la activación ya existían).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proveedor_sms', function (Blueprint $table): void {
            $table->id();
            $table->string('proveedor')->default('onurix');
            $table->text('onurix_cliente')->nullable();   // cifrado
            $table->text('onurix_clave')->nullable();      // cifrado
            $table->boolean('activo')->default(false);
            // Lo que Onurix cobra a la plataforma por segmento, y el precio de venta por defecto.
            $table->unsignedInteger('costo_segmento')->default(0);
            $table->unsignedInteger('precio_segmento')->default(0);
            $table->timestamps();
        });

        Schema::table('envios_mensaje', function (Blueprint $table): void {
            $table->unsignedSmallInteger('segmentos')->default(1)->after('estado');
            // Lo que se le cobra a la empresa y lo que le cuesta a la plataforma, por envío.
            $table->unsignedInteger('precio')->nullable()->after('segmentos');
            $table->unsignedInteger('costo')->nullable()->after('precio');
        });

        Schema::table('empresas', function (Blueprint $table): void {
            // Precio por segmento propio de la empresa; null = usa el precio por defecto global.
            $table->unsignedInteger('sms_precio')->nullable()->after('sms_limite_mensual');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedor_sms');

        Schema::table('envios_mensaje', function (Blueprint $table): void {
            $table->dropColumn(['segmentos', 'precio', 'costo']);
        });

        Schema::table('empresas', function (Blueprint $table): void {
            $table->dropColumn('sms_precio');
        });
    }
};
