<?php

namespace Tests\Feature\Pesv;

use App\Filament\App\Resources\Conductores\Pages\ListarConductores;
use App\Filament\App\Resources\Vehiculos\Pages\ListarVehiculos;
use App\Models\Conductor;
use App\Models\Vehiculo;
use App\Support\Pdf\PdfHojaVidaConductor;
use App\Support\Pdf\PdfHojaVidaVehiculo;
use App\Support\Pdf\SeccionHojaVida;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

/**
 * Hoja de vida en PDF del vehículo y del conductor, con secciones a elegir.
 */
class HojaVidaPdfTest extends ConfiguracionTestCase
{
    private function vehiculo(): Vehiculo
    {
        return Vehiculo::query()->deEmpresa($this->demo)->firstOrFail();
    }

    private function conductor(): Conductor
    {
        return Conductor::query()->deEmpresa($this->demo)->firstOrFail();
    }

    public function test_la_hoja_de_vida_del_vehiculo_sale_en_pdf_con_todas_las_secciones(): void
    {
        $this->entrarComo('admin@demo.test');

        $pdf = PdfHojaVidaVehiculo::generar($this->vehiculo(), array_keys(SeccionHojaVida::VEHICULO),
            now()->startOfYear(), now());

        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_solo_se_incluyen_las_secciones_elegidas(): void
    {
        $this->entrarComo('admin@demo.test');

        // Solo datos: no debe aparecer ninguna de las otras cabeceras.
        $html = view('pdf.hoja-vida-vehiculo', [
            'v' => $this->vehiculo()->loadMissing(['marca', 'linea', 'claseVehiculo', 'carroceria', 'combustible', 'tipoServicio', 'propietario', 'tenedor', 'organismoTransito']),
            'empresa' => $this->demo,
            'logo' => null,
            'desde' => now()->startOfYear(),
            'hasta' => now(),
            'secciones' => ['datos'],
            'etiquetas' => SeccionHojaVida::VEHICULO,
            'datos' => [],
        ])->render();

        $this->assertStringContainsString('Datos generales', $html);
        $this->assertStringNotContainsString('Mantenimientos', $html);
        $this->assertStringNotContainsString('Servicios prestados', $html);
    }

    public function test_el_pdf_tiene_encabezado_pie_y_evita_cortes(): void
    {
        $this->entrarComo('admin@demo.test');

        $html = view('pdf.hoja-vida-vehiculo', [
            'v' => $this->vehiculo()->loadMissing(['marca', 'linea']),
            'empresa' => $this->demo, 'logo' => null,
            'desde' => now()->startOfYear(), 'hasta' => now(),
            'secciones' => ['datos'], 'etiquetas' => SeccionHojaVida::VEHICULO, 'datos' => [],
        ])->render();

        $this->assertStringContainsString('<header>', $html);
        $this->assertStringContainsString('<footer>', $html);
        $this->assertStringContainsString('counter(page)', $html);           // número de página
        $this->assertStringContainsString('page-break-inside: avoid', $html); // no cortar bloques
        $this->assertStringContainsString('Hoja de vida del vehículo', $html);
    }

    public function test_la_hoja_de_vida_del_conductor_sale_en_pdf(): void
    {
        $this->entrarComo('admin@demo.test');

        $pdf = PdfHojaVidaConductor::generar($this->conductor(), array_keys(SeccionHojaVida::CONDUCTOR),
            now()->startOfYear(), now());

        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_la_accion_del_vehiculo_descarga_el_pdf(): void
    {
        $this->entrarComo('admin@demo.test');
        $vehiculo = $this->vehiculo();

        Livewire::test(ListarVehiculos::class)
            ->callAction(
                TestAction::make('hoja_vida_pdf')->table($vehiculo),
                ['secciones' => ['datos', 'documentos', 'preoperacionales'], 'desde' => now()->startOfYear()->toDateString(), 'hasta' => now()->toDateString()],
            )
            ->assertFileDownloaded(PdfHojaVidaVehiculo::nombre($vehiculo));
    }

    public function test_la_accion_del_conductor_descarga_el_pdf(): void
    {
        $this->entrarComo('admin@demo.test');
        $conductor = $this->conductor();

        Livewire::test(ListarConductores::class)
            ->callAction(
                TestAction::make('hoja_vida_pdf')->table($conductor),
                ['secciones' => ['datos', 'documentos'], 'desde' => now()->startOfYear()->toDateString(), 'hasta' => now()->toDateString()],
            )
            ->assertFileDownloaded(PdfHojaVidaConductor::nombre($conductor));
    }
}
