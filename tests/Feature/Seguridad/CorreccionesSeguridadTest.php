<?php

namespace Tests\Feature\Seguridad;

use App\Enums\ModuloEmpresa;
use App\Filament\App\Resources\TarifasSicetac\ImportarSicetac;
use App\Models\ManifiestoCarga;
use App\Models\Municipio;
use App\Models\RegistroRndc;
use App\Models\TarifaSicetac;
use App\Support\Carga\Sicetac;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

/**
 * Correcciones de la auditoría de seguridad del 2026-11-05.
 *
 * Cada prueba fija un fallo concreto que se encontró, para que no vuelva.
 */
class CorreccionesSeguridadTest extends ConfiguracionTestCase
{
    private function conCarga(): void
    {
        $this->demo->update(['modulos' => [...ModuloEmpresa::porDefecto(), 'carga']]);
    }

    public function test_sin_permiso_de_crear_no_se_ve_la_carga_masiva_del_sicetac(): void
    {
        $this->conCarga();

        // El auxiliar puede consultar el módulo de carga, pero no fijar los costos
        // mínimos: con $1 dejaría pasar cualquier flete por debajo del tope legal.
        $this->entrarComo('auxiliar@demo.test');
        $this->assertFalse(ImportarSicetac::accion()->isVisible());

        $this->flushSession();
        $this->entrarComo('admin@demo.test');
        $this->assertTrue(ImportarSicetac::accion()->isVisible());
    }

    public function test_la_carga_masiva_corta_en_el_tope_de_filas(): void
    {
        $this->conCarga();
        $this->entrarComo('admin@demo.test');

        // Hay ~1.120 municipios: se combinan con dos configuraciones para pasar de 2.000 filas.
        $dane = Municipio::query()->pluck('codigo_dane')->all();

        $lineas = [];
        foreach ([50, 54] as $configuracion) {
            foreach ($dane as $codigo) {
                $lineas[] = "{$codigo};11001;{$configuracion};2500000;;";
            }
        }

        $this->assertGreaterThan(2000, count($lineas));

        [$cargadas, $errores] = Sicetac::importar($this->demo->id, implode("\n", $lineas));

        $this->assertSame(2000, $cargadas);
        $this->assertStringContainsString('2000', implode(' ', $errores));
        $this->assertSame(2000, TarifaSicetac::query()->deEmpresa($this->demo)->count());
    }

    public function test_una_fecha_hasta_invalida_da_aviso_y_no_error_500(): void
    {
        $this->conCarga();
        $this->entrarComo('admin@demo.test');

        [$cargadas, $errores] = Sicetac::importar($this->demo->id, '23001;11001;50;2850000;2026-01-01;pasado mañana');

        $this->assertSame(0, $cargadas);
        $this->assertStringContainsString('fecha', implode(' ', $errores));
    }

    public function test_la_bitacora_rndc_abre_un_registro_con_grupos_anidados(): void
    {
        $this->conCarga();
        $this->entrarComo('admin@demo.test');

        // Así guarda el manifiesto sus remesas (proceso 4, grupo REMESASMAN).
        $registro = RegistroRndc::query()->create([
            'empresa_id' => $this->demo->id,
            'registrable_type' => ManifiestoCarga::class,
            'registrable_id' => 1,
            'proceso' => 4,
            'estado' => 'registrado',
            'ingresoid' => '777',
            'ambiente' => 'pruebas',
            'variables' => [
                'NUMMANIFIESTOCARGA' => '0001',
                'REMESASMAN' => ['@procesoid' => 43, 'items' => [['tag' => 'REMESA', 'vars' => ['CONSECUTIVOREMESA' => '0001']]]],
            ],
        ]);

        $html = view('filament.app.carga.detalle-registro-rndc', ['r' => $registro])->render();

        $this->assertStringContainsString('0001', $html);
        $this->assertStringContainsString('REMESA', $html);
    }

    public function test_el_portal_del_conductor_limita_los_intentos_por_documento(): void
    {
        // Seis intentos con la misma cédula: el sexto ya debe quedar bloqueado aunque
        // cambie la IP, porque el límite nuevo va por documento y no solo por IP.
        for ($i = 0; $i < 6; $i++) {
            $respuesta = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.'.$i])
                ->post(route('conductor.ingresar.enviar'), ['documento' => '20000001', 'clave' => 'incorrecta'.$i]);
        }

        $this->assertSame(429, $respuesta->getStatusCode());
    }

    public function test_las_trazas_no_llevan_los_argumentos_en_produccion(): void
    {
        // La clave del RNDC y las claves temporales de los conductores viajan como
        // argumentos de cadena; con los argumentos en la traza acababan en la tabla
        // de errores y en los logs.
        $ini = file_get_contents(base_path('docker/php.ini'));

        $this->assertStringContainsString('zend.exception_ignore_args = On', $ini);
        $this->assertStringContainsString('zend.exception_string_param_max_len = 0', $ini);
    }
}
