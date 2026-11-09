<?php

namespace Tests\Feature\Operacion;

use App\Actions\Operacion\ConvertirCotizacion;
use App\Enums\EstadoCotizacion;
use App\Filament\App\Resources\Cotizaciones\CotizacionResource;
use App\Filament\App\Resources\Cotizaciones\Pages\CrearCotizacion;
use App\Filament\App\Resources\Cotizaciones\Pages\ListarCotizaciones;
use App\Mail\CorreoCotizacion;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Support\Modulos;
use App\Support\Pdf\PdfCotizacion;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

/**
 * Cotizador de servicios: totales, consecutivo COT, PDF, envío por correo y conversión a contrato.
 */
class CotizacionTest extends ConfiguracionTestCase
{
    private function cotizacion(array $attrs = [], array $items = [['ruta' => 'Ruta A', 'valor' => 100000]]): Cotizacion
    {
        $c = new Cotizacion(array_merge([
            'numero' => 1, 'prospecto_nombre' => 'Hotel GHL', 'prospecto_email' => 'hotel@ghl.test',
            'ciudad' => 'Montería', 'fecha' => today(), 'valido_hasta' => today()->addDays(15),
            'asunto' => 'Traslados', 'estado' => 'borrador',
        ], $attrs));
        $c->empresa_id = $this->demo->id;
        $c->save();
        foreach ($items as $i => $it) {
            $c->items()->create([...$it, 'orden' => $i]);
        }

        return $c->fresh();
    }

    public function test_los_totales_aplican_descuento_e_iva(): void
    {
        $c = $this->cotizacion(['descuento' => 20000, 'aplica_iva' => true, 'iva_porcentaje' => 19], [
            ['ruta' => 'A', 'valor' => 150000],
            ['ruta' => 'B', 'valor' => 50000],
        ]);

        $this->assertSame(200000, $c->subtotal());
        $this->assertSame(180000, $c->baseGravable());      // 200000 - 20000
        $this->assertSame(34200, $c->iva());                // 19% de 180000
        $this->assertSame(214200, $c->total());
    }

    public function test_sin_iva_el_total_es_subtotal_menos_descuento(): void
    {
        $c = $this->cotizacion(['descuento' => 20000, 'aplica_iva' => false], [['ruta' => 'A', 'valor' => 4160000]]);

        $this->assertSame(0, $c->iva());
        $this->assertSame(4140000, $c->total());
    }

    public function test_el_numero_es_consecutivo_por_empresa(): void
    {
        $this->entrarComo('admin@demo.test');

        $crear = fn () => Livewire::test(CrearCotizacion::class)->fillForm([
            'prospecto_nombre' => 'Cliente X', 'fecha' => today(), 'valido_hasta' => today()->addDays(10),
            'items' => [['ruta' => 'Ruta', 'detalle' => '10 pax', 'valor' => 300000]],
        ])->call('create')->assertHasNoFormErrors();

        $crear();
        $crear();

        $numeros = Cotizacion::query()->deEmpresa($this->demo)->orderBy('numero')->pluck('numero')->all();
        $this->assertSame([1, 2], $numeros);
        $this->assertSame(EstadoCotizacion::Borrador, Cotizacion::query()->deEmpresa($this->demo)->first()->estado);
    }

    public function test_genera_el_pdf(): void
    {
        $c = $this->cotizacion();
        $pdf = PdfCotizacion::generar($c);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('COT-'.today()->format('Y'), $c->numero_formateado);
    }

    public function test_enviar_encola_el_correo_con_el_pdf(): void
    {
        Mail::fake();
        $this->entrarComo('admin@demo.test');
        $c = $this->cotizacion();

        Livewire::test(ListarCotizaciones::class)
            ->callAction(TestAction::make('enviar')->table($c));

        Mail::assertSent(CorreoCotizacion::class, fn (CorreoCotizacion $m) => $m->hasTo('hotel@ghl.test'));
        $this->assertSame(EstadoCotizacion::Enviada, $c->fresh()->estado);
    }

    public function test_convierte_en_contrato_y_no_dos_veces(): void
    {
        $c = $this->cotizacion(['descuento' => 20000], [['ruta' => 'A', 'valor' => 1000000]]);
        $cliente = Cliente::query()->deEmpresa($this->demo)->firstOrFail();

        $contrato = app(ConvertirCotizacion::class)($c, [
            'cliente_id' => $cliente->id, 'fecha_inicio' => today(), 'fecha_fin' => today()->addMonth(),
            'objeto' => 'turismo', 'forma_pago' => 'contado',
        ]);

        $this->assertSame(980000.0, (float) $contrato->valor);   // 1.000.000 - 20.000
        $this->assertSame($contrato->id, $c->fresh()->contrato_id);
        $this->assertSame(EstadoCotizacion::Aceptada, $c->fresh()->estado);

        $this->expectException(ValidationException::class);
        app(ConvertirCotizacion::class)($c->fresh(), [
            'cliente_id' => $cliente->id, 'fecha_inicio' => today(), 'fecha_fin' => today()->addMonth(), 'objeto' => 'turismo',
        ]);
    }

    public function test_vencida_cuando_pasa_la_validez(): void
    {
        $c = $this->cotizacion(['estado' => 'enviada', 'valido_hasta' => today()->subDay()]);
        $this->assertTrue($c->vencida());
        $this->assertSame('Vencida', $c->etiquetaEstado());
    }

    public function test_sin_el_modulo_pasajeros_no_aparece(): void
    {
        $this->entrarComo('admin@demo.test');
        // Quitar el módulo pasajeros oculta el recurso (gated por permiso del módulo).
        $this->demo->forceFill(['modulos' => ['mantenimiento']])->save();
        Modulos::olvidar($this->demo->id);

        $this->assertFalse(CotizacionResource::canViewAny());
    }
}
