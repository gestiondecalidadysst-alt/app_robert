<?php

namespace Tests\Feature\Carga;

use App\Enums\ModuloEmpresa;
use App\Filament\App\Resources\ManifiestosCarga\ManifiestoCargaResource;
use App\Filament\App\Resources\ManifiestosCarga\Pages\CrearManifiesto;
use App\Filament\App\Resources\ManifiestosCarga\Pages\EditarManifiesto;
use App\Filament\App\Resources\ManifiestosCarga\Pages\ListarManifiestos;
use App\Models\Conductor;
use App\Models\ManifiestoCarga;
use App\Models\Municipio;
use App\Models\Propietario;
use App\Models\RemesaCarga;
use App\Models\TerceroCarga;
use App\Models\TipoIdentificacion;
use App\Models\Vehiculo;
use App\Support\Carga\AsociadorDeRemesas;
use App\Support\Pdf\PdfManifiestoCarga;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

/**
 * El manifiesto se expide desde un asistente por pasos y la remesa se elige en el paso 3.
 * Estas pruebas recorren el formulario campo por campo (como el navegador) para que la
 * asociación no vuelva a perderse de forma silenciosa.
 */
class ManifiestoRemesasTest extends ConfiguracionTestCase
{
    private function prepararEmpresa(): void
    {
        $this->demo->update(['modulos' => [...ModuloEmpresa::porDefecto(), 'carga']]);
        $this->entrarComo('admin@demo.test');
    }

    private function remesa(string $producto = 'Arroz blanco'): RemesaCarga
    {
        $cc = TipoIdentificacion::where('codigo', 'CC')->value('id');
        $sufijo = (string) (RemesaCarga::query()->withoutGlobalScopes()->count() + 1);

        $remitente = TerceroCarga::create(['empresa_id' => $this->demo->id, 'tipo_identificacion_id' => $cc,
            'documento' => '9100'.$sufijo, 'nombre' => 'Remitente', 'primer_apellido' => 'Prueba',
            'celular' => '3001234567', 'direccion' => 'Calle 1', 'municipio_id' => Municipio::where('codigo_dane', '23001')->value('id')]);
        $destinatario = TerceroCarga::create(['empresa_id' => $this->demo->id, 'tipo_identificacion_id' => $cc,
            'documento' => '9200'.$sufijo, 'nombre' => 'Destinatario', 'primer_apellido' => 'Prueba',
            'celular' => '3001234567', 'direccion' => 'Calle 2', 'municipio_id' => Municipio::where('codigo_dane', '11001')->value('id')]);

        return RemesaCarga::create(['empresa_id' => $this->demo->id, 'fecha' => today(), 'operacion' => 'G',
            'empaque_codigo' => '6', 'naturaleza' => 1, 'producto_codigo' => '001504', 'producto_descripcion' => $producto,
            'cantidad' => 12000, 'unidad' => 1, 'remitente_id' => $remitente->id, 'destinatario_id' => $destinatario->id, 'dueno_poliza' => 'N']);
    }

    /** @return array{0: Vehiculo, 1: Conductor, 2: Propietario} */
    private function vehiculoListo(): array
    {
        $vehiculo = Vehiculo::query()->deEmpresa($this->demo)->where('placa', 'DMO101')->first();
        $vehiculo->update(['rndc_configuracion' => 50, 'peso_vacio' => 4000, 'rndc_marca' => '1',
            'rndc_linea' => '373', 'rndc_color' => '1', 'rndc_carroceria' => '2', 'rndc_combustible' => 1]);

        return [$vehiculo,
            Conductor::query()->deEmpresa($this->demo)->where('documento', '20000001')->first(),
            Propietario::query()->deEmpresa($this->demo)->first()];
    }

    /** @param  array<string, mixed>  $extra */
    private function llenarAsistente(array $extra): Testable
    {
        [$vehiculo, $conductor, $propietario] = $this->vehiculoListo();

        $campos = [
            'tipo' => 'G',
            'fecha_expedicion' => today()->toDateString(),
            'origen_id' => Municipio::where('codigo_dane', '76001')->value('id'),
            'destino_id' => Municipio::where('codigo_dane', '11001')->value('id'),
            'vehiculo_id' => $vehiculo->id,
            'conductor_id' => $conductor->id,
            'titular_propietario_id' => $propietario->id,
            'valor_flete' => 2500000,
            'anticipo' => 500000,
            'retencion_fuente_pct' => 1,
            'reteica_por_mil' => 0,
            'retencion_fopat_pct' => 1,
            'responsable_cargue' => 'E',
            'responsable_descargue' => 'E',
            ...$extra,
        ];

        $prueba = Livewire::test(CrearManifiesto::class);

        // Campo por campo, igual que el navegador al avanzar por los pasos del asistente.
        foreach ($campos as $campo => $valor) {
            $prueba->set("data.{$campo}", $valor);
        }

        return $prueba->call('create');
    }

    public function test_la_remesa_elegida_queda_asociada_al_manifiesto(): void
    {
        $this->prepararEmpresa();
        $remesa = $this->remesa();

        $this->llenarAsistente(['remesas_ids' => [$remesa->id]])->assertHasNoFormErrors();

        $manifiesto = ManifiestoCarga::query()->latest('id')->first();

        $this->assertNotNull($manifiesto);
        $this->assertSame([$remesa->id], $manifiesto->remesas()->pluck('id')->all());
        $this->assertSame($manifiesto->id, $remesa->fresh()->manifiesto_id);
    }

    public function test_el_pdf_muestra_la_mercancia_de_la_remesa(): void
    {
        $this->prepararEmpresa();
        $remesa = $this->remesa('Café pergamino seco');

        $this->llenarAsistente(['remesas_ids' => [$remesa->id]])->assertHasNoFormErrors();

        $html = PdfManifiestoCarga::html(ManifiestoCarga::query()->latest('id')->first());

        $this->assertStringContainsString('Café pergamino seco', $html);
        $this->assertStringContainsString('Remitente', $html);
    }

    public function test_sin_remesa_no_se_expide_el_manifiesto(): void
    {
        $this->prepararEmpresa();
        $this->remesa();

        $this->llenarAsistente(['remesas_ids' => []]);

        $this->assertSame(0, ManifiestoCarga::query()->count());
    }

    public function test_al_editar_se_cambia_la_remesa_y_se_libera_la_anterior(): void
    {
        $this->prepararEmpresa();
        $primera = $this->remesa('Maíz amarillo');
        $segunda = $this->remesa('Soya en grano');

        $this->llenarAsistente(['remesas_ids' => [$primera->id]])->assertHasNoFormErrors();
        $manifiesto = ManifiestoCarga::query()->latest('id')->first();

        Livewire::test(EditarManifiesto::class, ['record' => $manifiesto->getRouteKey()])
            ->set('data.remesas_ids', [$segunda->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([$segunda->id], $manifiesto->fresh()->remesas()->pluck('id')->all());
        $this->assertNull($primera->fresh()->manifiesto_id);
    }

    public function test_la_accion_remesas_repara_un_manifiesto_sin_remesa(): void
    {
        $this->prepararEmpresa();
        $remesa = $this->remesa('Panela en bloque');

        $this->llenarAsistente(['remesas_ids' => [$remesa->id]])->assertHasNoFormErrors();
        $manifiesto = ManifiestoCarga::query()->latest('id')->first();

        // Simula el daño del código anterior: manifiesto expedido sin remesa.
        $remesa->update(['manifiesto_id' => null]);
        $this->assertSame(0, $manifiesto->remesas()->count());

        Livewire::test(ListarManifiestos::class)
            ->callAction(TestAction::make('remesas')->table($manifiesto), ['remesas_ids' => [$remesa->id]])
            ->assertHasNoActionErrors();

        $this->assertSame([$remesa->id], $manifiesto->fresh()->remesas()->pluck('id')->all());
    }

    public function test_la_asociacion_no_depende_de_la_empresa_activa_del_panel(): void
    {
        $this->prepararEmpresa();
        $remesa = $this->remesa('Cemento gris');

        $this->llenarAsistente(['remesas_ids' => [$remesa->id]])->assertHasNoFormErrors();
        $manifiesto = ManifiestoCarga::query()->latest('id')->first();

        $remesa->update(['manifiesto_id' => null]);

        // Sin empresa activa (cola, consola o cualquier contexto fuera del panel)
        // el global scope no filtra: la asociación debe seguir funcionando igual.
        Filament::setTenant(null);

        $asociadas = AsociadorDeRemesas::aplicar($manifiesto, [$remesa->id]);

        $this->assertSame(1, $asociadas);
        $this->assertSame($manifiesto->id, $remesa->fresh()->manifiesto_id);
    }

    public function test_el_selector_explica_cuando_no_hay_remesas_disponibles(): void
    {
        $this->prepararEmpresa();
        $remesa = $this->remesa('Azúcar');
        $this->llenarAsistente(['remesas_ids' => [$remesa->id]])->assertHasNoFormErrors();

        $otra = ManifiestoCarga::query()->latest('id')->first();
        $this->assertSame(1, $otra->remesas()->count());

        // Todas las remesas están ya en un manifiesto: el selector de otro manifiesto debe decirlo.
        $metodo = new \ReflectionMethod(ManifiestoCargaResource::class, 'porqueNoHayRemesas');
        $metodo->setAccessible(true);
        $vacio = new ManifiestoCarga;
        $vacio->id = 999999;

        $this->assertStringContainsString('otro manifiesto', $metodo->invoke(null, $vacio));
    }

    public function test_si_la_asociacion_falla_el_manifiesto_no_se_expide(): void
    {
        $this->prepararEmpresa();
        $remesa = $this->remesa('Harina');

        // Remesa anulada: el selector la ofrece pero la asociación debe rechazarla,
        // y entonces el manifiesto no puede quedar expedido vacío.
        $remesa->update(['anulado_at' => now()]);

        $this->llenarAsistente(['remesas_ids' => [$remesa->id]]);

        $this->assertSame(0, ManifiestoCarga::query()->count());
    }
}
