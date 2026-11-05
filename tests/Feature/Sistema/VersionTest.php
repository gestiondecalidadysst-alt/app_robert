<?php

namespace Tests\Feature\Sistema;

use App\Support\Sistema\Version;
use Tests\TestCase;

/** La versión desplegada debe verse siempre: es lo que dice si un arreglo ya llegó al servidor. */
class VersionTest extends TestCase
{
    public function test_el_resumen_nunca_queda_vacio(): void
    {
        $this->assertStringStartsWith('Versión ', Version::resumen());
    }

    public function test_la_variable_de_entorno_manda(): void
    {
        config(['app.version' => 'abc1234']);

        $this->assertSame('abc1234', Version::commit());
        $this->assertStringContainsString('abc1234', Version::resumen());
    }

    public function test_la_pantalla_de_acceso_muestra_la_version(): void
    {
        config(['app.version' => 'abc1234']);

        $this->get('/admin/login')->assertOk()->assertSee('abc1234');
    }
}
