<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plantilla de contrato con versiones, documento del contrato emitido (copia congelada),
 * firma electrónica (códigos y eventos), historial de mensajes y configuración de
 * integraciones de cada empresa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('representante_expedicion')->nullable()->after('representante_documento');
            $table->string('sitio_web')->nullable()->after('email');
            $table->string('firma_representante_path')->nullable()->after('logo_path'); // disco privado
            $table->string('pie_documentos_path')->nullable()->after('firma_representante_path'); // certificaciones, sellos

            // Integraciones (credenciales cifradas con APP_KEY)
            $table->boolean('sms_habilitado')->default(false);
            $table->text('onurix_cliente')->nullable();
            $table->text('onurix_clave')->nullable();
            $table->unsignedInteger('sms_limite_mensual')->nullable();
            $table->boolean('firma_envio_automatico')->default(false);
            $table->unsignedTinyInteger('firma_dias_enlace')->default(7);
            $table->unsignedTinyInteger('firma_dias_recordatorio')->default(2);
        });

        Schema::create('plantillas_contrato', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->string('codigo_formato', 40)->nullable(); // control de documentos (ISO), p. ej. GPS-PR01-FT03
            $table->string('titulo')->default('FORMATO DE CONTRATO DE PRESTACIÓN DE SERVICIO');
            $table->longText('contenido'); // HTML con variables (merge tags del editor)
            $table->text('notas_cambio')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['empresa_id', 'version']);
        });

        Schema::create('documentos_contrato', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('contrato_id')->constrained('contratos')->cascadeOnDelete();
            $table->foreignId('plantilla_contrato_id')->nullable()->constrained('plantillas_contrato')->nullOnDelete();
            $table->unsignedSmallInteger('version_plantilla');
            $table->string('codigo_formato', 40)->nullable();

            // Copia congelada al emitir
            $table->longText('contenido'); // HTML ya diligenciado
            $table->json('datos'); // valores de las variables, encabezado y pie
            $table->string('huella_contenido', 64); // SHA-256 del contenido y los datos
            $table->string('firma_contratista_path')->nullable(); // copia de la firma del representante

            $table->string('estado', 20)->default('emitido'); // emitido | enviado | visto | firmado | anulado
            $table->string('codigo_verificacion', 32)->unique(); // QR público

            // Enlace de firma (solo se guarda la huella del token)
            $table->string('token_hash', 64)->nullable()->unique();
            $table->text('token_cifrado')->nullable(); // para reenviar el mismo enlace (recordatorios)
            $table->timestamp('token_expira_at')->nullable();

            // Firmante
            $table->string('firmante_nombre')->nullable();
            $table->string('firmante_documento', 30)->nullable();
            $table->string('firmante_celular', 20)->nullable();
            $table->string('firmante_email')->nullable();

            // Evidencia de la firma
            $table->timestamp('enviado_at')->nullable();
            $table->timestamp('visto_at')->nullable();
            $table->timestamp('firmado_at')->nullable();
            $table->string('firma_path')->nullable(); // imagen de la firma del contratante (privada)
            $table->string('firma_ip', 45)->nullable();
            $table->text('firma_user_agent')->nullable();
            $table->string('firma_canal_codigo', 10)->nullable(); // sms | correo
            $table->string('firma_destino_codigo')->nullable(); // enmascarado
            $table->string('pdf_firmado_path')->nullable();
            $table->string('huella_pdf', 64)->nullable(); // SHA-256 del PDF firmado

            $table->timestamp('recordatorio_enviado_at')->nullable();
            $table->timestamp('vencimiento_avisado_at')->nullable();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anulado_at')->nullable();
            $table->text('motivo_anulacion')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['contrato_id', 'estado']);
            $table->index(['empresa_id', 'estado']);
        });

        Schema::create('codigos_firma', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_contrato_id')->constrained('documentos_contrato')->cascadeOnDelete();
            $table->string('canal', 10); // sms | correo
            $table->string('destino'); // enmascarado
            $table->string('codigo_hash', 64);
            $table->timestamp('expira_at');
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->timestamp('usado_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['documento_contrato_id', 'created_at']);
        });

        Schema::create('eventos_documento_contrato', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_contrato_id')->constrained('documentos_contrato')->cascadeOnDelete();
            $table->string('evento', 30);
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('detalle')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['documento_contrato_id', 'created_at']);
        });

        Schema::create('envios_mensaje', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('documento_contrato_id')->nullable()->constrained('documentos_contrato')->cascadeOnDelete();
            $table->string('tipo', 30); // enlace_firma | codigo_firma | contrato_firmado | recordatorio_firma | prueba
            $table->string('canal', 10); // sms | correo
            $table->string('destino'); // enmascarado
            $table->string('estado', 15)->default('pendiente'); // pendiente | enviado | fallido | omitido
            $table->string('proveedor', 30)->nullable();
            $table->string('referencia_proveedor')->nullable();
            $table->text('error')->nullable();
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->timestamp('enviado_at')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'canal', 'created_at']);
        });

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('envios_mensaje');
        Schema::dropIfExists('eventos_documento_contrato');
        Schema::dropIfExists('codigos_firma');
        Schema::dropIfExists('documentos_contrato');
        Schema::dropIfExists('plantillas_contrato');

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn([
                'representante_expedicion', 'sitio_web', 'firma_representante_path', 'pie_documentos_path',
                'sms_habilitado', 'onurix_cliente', 'onurix_clave', 'sms_limite_mensual',
                'firma_envio_automatico', 'firma_dias_enlace', 'firma_dias_recordatorio',
            ]);
        });
    }
};
