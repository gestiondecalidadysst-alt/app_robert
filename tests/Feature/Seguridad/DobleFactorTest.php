<?php

namespace Tests\Feature\Seguridad;

use Illuminate\Support\Facades\DB;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

/**
 * Segundo factor (TOTP) de los usuarios del panel: el secreto y los códigos de recuperación
 * se guardan y recuperan bien, y quedan cifrados en reposo.
 */
class DobleFactorTest extends ConfiguracionTestCase
{
    public function test_guarda_y_recupera_el_secreto_y_los_codigos_cifrados(): void
    {
        $usuario = $this->usuario('admin@demo.test');

        $usuario->saveAppAuthenticationSecret('SECRETO-TOTP-123');
        $usuario->saveAppAuthenticationRecoveryCodes(['uno', 'dos', 'tres']);
        $usuario->refresh();

        // Round-trip correcto a través de los contratos de Filament.
        $this->assertSame('SECRETO-TOTP-123', $usuario->getAppAuthenticationSecret());
        $this->assertSame(['uno', 'dos', 'tres'], $usuario->getAppAuthenticationRecoveryCodes());

        // Cifrado en reposo: la columna no guarda el texto plano.
        $crudo = DB::table('users')->where('id', $usuario->id)->value('app_authentication_secret');
        $this->assertNotSame('SECRETO-TOTP-123', $crudo);
        $this->assertNotEmpty($crudo);

        // El secreto y los códigos quedan ocultos al serializar el modelo.
        $this->assertArrayNotHasKey('app_authentication_secret', $usuario->toArray());
        $this->assertArrayNotHasKey('app_authentication_recovery_codes', $usuario->toArray());
    }
}
