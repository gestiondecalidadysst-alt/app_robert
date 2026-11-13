<?php

namespace Tests\Feature\Operacion;

use App\Models\Fuec;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

/**
 * Regresión del incidente de producción: un FUEC cuyo snapshot 'datos' no trae
 * las claves que la pantalla espera (p. ej. un FUEC migrado desde la app anterior)
 * hacía que la lista y la ficha fallaran con "Undefined array key". Las pantallas
 * deben tolerar datos incompletos y mostrar un guion en lugar de romperse.
 */
class FuecDatosIncompletosTest extends ConfiguracionTestCase
{
    private function fuecDeLaDemo(): Fuec
    {
        return Fuec::query()
            ->withoutGlobalScopes()
            ->where('empresa_id', $this->demo->id)
            ->firstOrFail();
    }

    public function test_la_lista_de_fuec_abre_aunque_un_fuec_tenga_datos_vacios(): void
    {
        $this->actingAs($this->usuario('admin@demo.test'));

        $fuec = $this->fuecDeLaDemo();
        $fuec->forceFill(['datos' => []])->saveQuietly();

        $this->get('/app/cootrasec-demo/fuec')->assertOk();
    }

    public function test_la_ficha_de_fuec_abre_aunque_los_datos_esten_incompletos(): void
    {
        $this->actingAs($this->usuario('admin@demo.test'));

        $fuec = $this->fuecDeLaDemo();
        // Solo faltan las claves del contrato y la ruta (caso típico del FUEC migrado).
        $fuec->forceFill(['datos' => ['vehiculo' => ['placa' => 'ABC123']]])->saveQuietly();

        $this->get("/app/cootrasec-demo/fuec/{$fuec->id}")->assertOk();
    }
}
