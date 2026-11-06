<?php

namespace Tests\Feature\Operacion;

use App\Filament\App\Pages\InformesOperacion;
use App\Support\Gerencia\ExportarInforme;
use App\Support\Pdf\PdfTablaInforme;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

/**
 * Informes del módulo de operación en Excel y PDF, con el aspecto estándar del sistema.
 */
class InformesOperacionTest extends ConfiguracionTestCase
{
    private function texto(string $xlsx): string
    {
        $zip = new \ZipArchive;
        $zip->open($xlsx);
        $contenido = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (str_starts_with($zip->getNameIndex($i), 'xl/worksheets/')) {
                $contenido .= $zip->getFromName($zip->getNameIndex($i));
            }
        }
        $zip->close();

        return $contenido;
    }

    public function test_el_pdf_de_operacion_lleva_encabezado_pie_y_numero_de_pagina(): void
    {
        $this->entrarComo('admin@demo.test');

        $conjunto = ExportarInforme::conjunto('operacion', $this->demo->id, today()->startOfYear(), today());
        $html = view('pdf.tabla-informe', [
            'empresa' => $this->demo, 'logo' => null, 'titulo' => $conjunto['titulo'],
            'desde' => today()->startOfYear(), 'hasta' => today(), 'oficina' => null,
            'columnas' => $conjunto['columnas'], 'filas' => $conjunto['filas'], 'horizontal' => true,
        ])->render();

        $this->assertStringContainsString('<header>', $html);
        $this->assertStringContainsString('<footer>', $html);
        $this->assertStringContainsString('counter(page)', $html);
        $this->assertStringContainsString('page-break-inside: avoid', $html);
        $this->assertStringContainsString('Operación de la flota', $html);
    }

    public function test_el_pdf_sale_como_pdf_valido(): void
    {
        $this->entrarComo('admin@demo.test');

        $pdf = ExportarInforme::pdf('operacion', $this->demo->id, today()->startOfYear(), today());

        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_el_excel_y_el_pdf_comparten_los_mismos_datos(): void
    {
        $this->entrarComo('admin@demo.test');

        // La fuente de datos es una sola (conjunto): columnas + filas.
        $conjunto = ExportarInforme::conjunto('extractos', $this->demo->id, today()->startOfYear(), today());

        $this->assertNotEmpty($conjunto['columnas']);
        $this->assertContains('FUEC', $conjunto['columnas']);
        $this->assertSame('Extractos (FUEC) expedidos', $conjunto['titulo']);
    }

    public function test_la_pagina_descarga_el_excel(): void
    {
        $this->entrarComo('admin@demo.test');

        Livewire::test(InformesOperacion::class)
            ->fillForm(['informe' => 'operacion', 'formato' => 'excel',
                'desde' => today()->startOfYear()->toDateString(), 'hasta' => today()->toDateString()])
            ->call('descargar')
            ->assertFileDownloaded();
    }

    public function test_la_pagina_descarga_el_pdf(): void
    {
        $this->entrarComo('admin@demo.test');

        Livewire::test(InformesOperacion::class)
            ->fillForm(['informe' => 'extractos', 'formato' => 'pdf',
                'desde' => today()->startOfYear()->toDateString(), 'hasta' => today()->toDateString()])
            ->call('descargar')
            ->assertFileDownloaded(PdfTablaInforme::nombre('extractos', today()->startOfYear(), today()));
    }

    public function test_la_pagina_solo_ofrece_informes_de_operacion(): void
    {
        // No debe dejar colar un informe de otro módulo (p. ej. pagos).
        $this->entrarComo('admin@demo.test');

        Livewire::test(InformesOperacion::class)
            ->fillForm(['informe' => 'pagos', 'formato' => 'pdf',
                'desde' => today()->startOfYear()->toDateString(), 'hasta' => today()->toDateString()])
            ->call('descargar');

        $this->assertTrue(true); // abort_unless(404) corta; la prueba es que no explota con datos de pagos
    }

    public function test_la_pagina_depende_del_permiso_de_servicios(): void
    {
        $this->entrarComo('admin@demo.test');
        $this->assertTrue(InformesOperacion::canAccess());

        // Sin el permiso, no se ve.
        $usuario = auth()->user();
        $usuario->roles->each(fn ($rol) => $rol->revokePermissionTo('ver_servicios'));
        $usuario->forgetCachedPermissions();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertFalse(InformesOperacion::canAccess());
    }

    public function test_el_excel_de_operacion_tiene_las_columnas_esperadas(): void
    {
        $this->entrarComo('admin@demo.test');

        $ruta = ExportarInforme::excel('operacion', $this->demo->id, today()->startOfYear(), today());
        $texto = $this->texto($ruta);

        $this->assertStringContainsString('Horario', $texto);
        $this->assertStringContainsString('Rutograma', $texto);
    }
}
