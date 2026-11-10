<?php

namespace Tests\Feature\Sistema;

use App\Models\ErrorSistema;
use App\Models\OcurrenciaError;
use App\Support\Sistema\Referencia;
use App\Support\Sistema\RegistroErrores;
use RuntimeException;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

/**
 * Historial de ocurrencias: el error se sigue agrupando por huella, pero queda el rastro de cada
 * vez que ocurrió, para diagnosticar y para encontrarlo por cualquier referencia reportada.
 */
class HistorialErroresTest extends ConfiguracionTestCase
{
    public function test_cada_ocurrencia_queda_registrada_con_su_referencia(): void
    {
        $excepcion = new RuntimeException('Falla de prueba');

        Referencia::reiniciar();
        $primera = Referencia::actual();
        RegistroErrores::registrar($excepcion);

        Referencia::reiniciar();
        $segunda = Referencia::actual();
        RegistroErrores::registrar($excepcion);

        $this->assertNotSame($primera, $segunda);

        // Un solo error agrupado, con dos ocurrencias.
        $this->assertSame(1, ErrorSistema::query()->count());
        $error = ErrorSistema::query()->firstOrFail();
        $this->assertSame(2, $error->ocurrencias);
        $this->assertSame(2, $error->ocurrencias()->count());

        // Ambas referencias quedaron en el historial.
        $referencias = $error->ocurrencias()->pluck('referencia')->all();
        $this->assertContains($primera, $referencias);
        $this->assertContains($segunda, $referencias);
    }

    public function test_se_encuentra_el_error_por_una_referencia_antigua(): void
    {
        $excepcion = new RuntimeException('Otra falla');

        Referencia::reiniciar();
        $antigua = Referencia::actual();
        RegistroErrores::registrar($excepcion);

        Referencia::reiniciar();
        RegistroErrores::registrar($excepcion);

        // La columna del error ya guarda la referencia nueva...
        $error = ErrorSistema::query()->firstOrFail();
        $this->assertNotSame($antigua, $error->referencia);

        // ...pero la antigua sigue encontrando el error por el historial.
        $encontrado = ErrorSistema::query()
            ->whereHas('ocurrencias', fn ($q) => $q->where('referencia', $antigua))
            ->first();

        $this->assertNotNull($encontrado);
        $this->assertSame($error->id, $encontrado->id);
    }

    public function test_el_historial_se_poda_y_no_crece_sin_limite(): void
    {
        $excepcion = new RuntimeException('Falla repetida');
        $tope = RegistroErrores::OCURRENCIAS_POR_ERROR;

        for ($i = 0; $i < $tope + 5; $i++) {
            Referencia::reiniciar();
            RegistroErrores::registrar($excepcion);
        }

        $error = ErrorSistema::query()->firstOrFail();
        $this->assertSame($tope + 5, $error->ocurrencias, 'El contador sí cuenta todas.');
        $this->assertSame($tope, OcurrenciaError::query()->count(), 'El historial se poda al tope.');
    }
}
