<?php

namespace Tests\Feature\Pesv;

use App\Models\Fuec;
use App\Models\Inspeccion;
use App\Models\PlanAccion;
use App\Models\Vehiculo;
use App\Support\Gerencia\ExportarInforme;
use Illuminate\Support\Str;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

/**
 * El preoperacional se hace contra un FUEC (el extracto del contrato). El informe debe
 * dejar ver para qué extracto y contrato se hizo, no solo el vehículo y el conductor.
 */
class PreoperacionalExtractoTest extends ConfiguracionTestCase
{
    private function texto(string $xlsx): string
    {
        // El xlsx es un zip; openspout escribe las cadenas en línea en la hoja, no en
        // sharedStrings, así que se leen todas las hojas.
        $zip = new \ZipArchive;
        $zip->open($xlsx);
        $contenido = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombre = $zip->getNameIndex($i);
            if (str_starts_with($nombre, 'xl/worksheets/')) {
                $contenido .= $zip->getFromName($nombre);
            }
        }
        $zip->close();

        return $contenido;
    }

    private function preoperacional(): Inspeccion
    {
        $this->entrarComo('admin@demo.test');
        $fuec = Fuec::query()->deEmpresa($this->demo)->with('contrato')->firstOrFail();

        // Se crea directo con el FUEC enlazado y su snapshot, como lo deja RegistrarInspeccion.
        $preop = new Inspeccion([
            'tipo' => 'preoperacional',
            'vehiculo_id' => $fuec->vehiculo_id,
            'fuec_id' => $fuec->id,
            'fecha' => today(),
            'kilometraje' => 150000,
            'resultado' => 'apto',
            'respuestas' => [],
            'datos' => ['fuec' => ['numero' => $fuec->numero, 'contrato' => $fuec->contrato?->numero_formateado]],
            'codigo_verificacion' => Str::lower(Str::random(24)),
        ]);
        $preop->empresa_id = $this->demo->id;
        $preop->save();

        return $preop;
    }

    public function test_el_preoperacional_expone_el_extracto_y_el_contrato(): void
    {
        $preop = $this->preoperacional();

        $this->assertNotNull($preop->numeroExtracto(), 'Debe tener número de extracto (FUEC)');
        $this->assertNotNull($preop->numeroContrato(), 'Debe tener número de contrato');
    }

    public function test_el_excel_de_preoperacionales_incluye_extracto_y_contrato(): void
    {
        $this->preoperacional();

        $ruta = ExportarInforme::excel('preoperacionales', $this->demo->id, today()->startOfMonth(), today()->endOfMonth());
        $texto = $this->texto($ruta);

        $this->assertStringContainsString('Extracto (FUEC)', $texto, 'La cabecera debe traer el extracto');
        $this->assertStringContainsString('Contrato', $texto);
    }

    public function test_el_informe_muestra_el_cierre_del_hallazgo(): void
    {
        $this->entrarComo('admin@demo.test');
        $vehiculo = Vehiculo::query()->deEmpresa($this->demo)->first();

        $preop = new Inspeccion([
            'tipo' => 'preoperacional', 'vehiculo_id' => $vehiculo->id, 'fecha' => today(),
            'resultado' => 'no_apto', 'respuestas' => [], 'datos' => [], 'observaciones' => 'Falla en frenos',
            'codigo_verificacion' => Str::lower(Str::random(24)),
        ]);
        $preop->empresa_id = $this->demo->id;
        $preop->save();

        $plan = new PlanAccion([
            'vehiculo_id' => $vehiculo->id, 'origen' => 'preoperacional', 'inspeccion_id' => $preop->id,
            'prioridad' => 'alta', 'titulo' => 'Frenos', 'hallazgos' => [['descripcion' => 'Frenos']],
            'accion' => 'Cambiar pastillas', 'estado' => 'cerrado',
        ]);
        $plan->empresa_id = $this->demo->id;
        $plan->forceFill(['cerrado_at' => now(), 'observacion_cierre' => 'Se cambiaron las pastillas de freno'])->save();

        $texto = $this->texto(ExportarInforme::excel('preoperacionales', $this->demo->id, today()->startOfMonth(), today()->endOfMonth()));

        $this->assertStringContainsString('Cierre del hallazgo', $texto, 'La cabecera debe incluir el cierre');
        $this->assertStringContainsString('Se cambiaron las pastillas de freno', $texto, 'Debe mostrar qué se hizo para cerrar');
        $this->assertStringContainsString('Cerrado', $texto, 'Debe mostrar el estado del hallazgo');
    }

    public function test_sin_fuec_el_extracto_queda_vacio_sin_romper(): void
    {
        $this->entrarComo('admin@demo.test');
        $vehiculo = Vehiculo::query()->deEmpresa($this->demo)->first();

        $preop = new Inspeccion([
            'tipo' => 'preoperacional', 'vehiculo_id' => $vehiculo->id, 'fecha' => today(),
            'resultado' => 'apto', 'respuestas' => [], 'datos' => [],
            'codigo_verificacion' => Str::lower(Str::random(24)),
        ]);
        $preop->empresa_id = $this->demo->id;
        $preop->save();

        $this->assertNull($preop->numeroExtracto());
        $this->assertNull($preop->numeroContrato());
    }
}
