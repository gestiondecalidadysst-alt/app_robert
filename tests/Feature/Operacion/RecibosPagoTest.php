<?php

namespace Tests\Feature\Operacion;

use App\Actions\Operacion\AnularPago;
use App\Actions\Operacion\CrearContrato;
use App\Actions\Operacion\EnviarRecibo;
use App\Actions\Operacion\RegistrarPago;
use App\Enums\MetodoPago;
use App\Enums\ObjetoContrato;
use App\Filament\App\Resources\Contratos\Pages\VerContrato;
use App\Filament\App\Resources\Contratos\RelationManagers\PagosRelationManager;
use App\Mail\CorreoReciboPago;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\EnvioMensaje;
use App\Models\PagoContrato;
use App\Support\Pdf\PdfRecibo;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

class RecibosPagoTest extends ConfiguracionTestCase
{
    private function contrato(int $valor = 1000000): Contrato
    {
        return app(CrearContrato::class)($this->demo, [
            'cliente_id' => Cliente::query()->deEmpresa($this->demo)->firstOrFail()->id,
            'objeto' => ObjetoContrato::Turismo,
            'fecha_inicio' => today()->toDateString(),
            'fecha_fin' => today()->addDays(3)->toDateString(),
            'valor' => $valor,
        ]);
    }

    private function pagar(Contrato $contrato, int $valor): PagoContrato
    {
        return app(RegistrarPago::class)($contrato, ['fecha' => today()->toDateString(), 'valor' => $valor, 'metodo' => MetodoPago::Efectivo]);
    }

    public function test_cada_pago_tiene_recibo_consecutivo_con_su_tipo_y_saldos(): void
    {
        $this->entrarComo('admin@demo.test');

        // Los pagos de la demo: un abono (recibo 1) y un pago total (recibo 2).
        $demo = PagoContrato::query()->deEmpresa($this->demo)->orderBy('numero')->get();
        $this->assertSame([1, 2], $demo->pluck('numero')->all());
        $this->assertSame([PagoContrato::ABONO, PagoContrato::PAGO_TOTAL], $demo->map->tipo()->all());
        $this->assertSame('RC-0001', $demo[0]->numero_recibo);

        $contrato = $this->contrato();
        $abono = $this->pagar($contrato, 400000);
        $this->assertSame(3, $abono->numero);
        $this->assertSame(PagoContrato::ABONO, $abono->tipo());
        $this->assertEquals(1000000, $abono->saldo_anterior);
        $this->assertEquals(600000, $abono->saldo_despues);
        $this->assertFalse($contrato->refresh()->pagado());

        $final = $this->pagar($contrato, 600000);
        $this->assertSame(PagoContrato::ABONO_FINAL, $final->tipo());
        $this->assertEquals(0, $final->saldo_despues);
        $this->assertTrue($contrato->refresh()->pagado());
        $this->assertThrows(fn () => $this->pagar($contrato, 1), ValidationException::class);

        // Consecutivo independiente por empresa.
        $otro = app(CrearContrato::class)($this->otra, ['cliente_id' => Cliente::create(['empresa_id' => $this->otra->id, 'documento' => '1', 'nombre' => 'X'])->id,
            'objeto' => ObjetoContrato::Turismo, 'fecha_inicio' => today(), 'fecha_fin' => today(), 'valor' => 100]);
        $this->assertSame(1, $this->pagar($otro, 100)->numero);
    }

    public function test_anular_un_pago_lo_saca_del_saldo_sin_reusar_su_numero(): void
    {
        $this->entrarComo('admin@demo.test');
        $contrato = $this->contrato();
        $pago = $this->pagar($contrato, 1000000);
        $this->assertTrue($contrato->refresh()->pagado());

        $this->assertThrows(fn () => app(AnularPago::class)($pago, ''), ValidationException::class);
        app(AnularPago::class)($pago, 'Transferencia rechazada');

        $this->assertTrue($pago->refresh()->anulado());
        $this->assertSame(1000000.0, $contrato->refresh()->saldo());
        $this->assertSame($pago->numero + 1, $this->pagar($contrato, 1000000)->numero, 'El número anulado no se reutiliza.');
        $this->assertThrows(fn () => app(AnularPago::class)($pago, 'Otra vez'), ValidationException::class);

        // El listado de contratos suma solo los pagos vigentes.
        $this->assertSame(1000000.0, (float) Contrato::query()->withSum(['pagos' => fn ($q) => $q->whereNull('anulado_at')], 'valor')->find($contrato->id)->pagos_sum_valor);
    }

    public function test_prefijo_y_numero_inicial_de_la_empresa(): void
    {
        $this->entrarComo('admin@demo.test');
        $this->demo->update(['recibo_prefijo' => 'CJ', 'recibo_numero_inicial' => 500]);

        $pago = $this->pagar($this->contrato(), 1000);
        $this->assertSame(500, $pago->numero);
        $this->assertSame('CJ-0500', $pago->numero_recibo);
        $this->assertSame(501, $this->pagar($this->contrato(), 1000)->numero);
    }

    public function test_pdf_y_verificacion_publica_del_recibo(): void
    {
        $this->entrarComo('admin@demo.test');
        $contrato = $this->contrato();
        $pago = $this->pagar($contrato, 250000);

        $this->assertStringStartsWith('%PDF', PdfRecibo::recibo($pago));
        $this->assertStringStartsWith('%PDF', PdfRecibo::estadoCuenta($contrato->refresh()));
        $html = view('pdf.recibo-pago', [
            'pago' => $pago, 'contrato' => $contrato, 'empresa' => $this->demo, 'logo' => null, 'qr' => '',
            'v' => \App\Support\Contratos\VariablesContrato::valores($contrato, $this->demo),
            'letras' => 'DOSCIENTOS CINCUENTA MIL PESOS M/CTE', 'abonadoAntes' => 0,
        ])->render();
        $this->assertStringContainsString('RECIBO DE CAJA', $html);
        $this->assertStringContainsString('Abono al contrato', $html);

        auth()->logout();
        $this->get($pago->urlVerificacion())->assertOk()->assertSee($pago->numero_recibo)->assertSee('Válido');
        $this->get($pago->urlPdf())->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/verificar/recibo/' . str_repeat('a', 24))->assertNotFound();

        app(AnularPago::class)($pago, 'Error de digitación');
        $this->get($pago->urlVerificacion())->assertOk()->assertSee('Anulado');
    }

    public function test_paz_y_salvo_y_envio_al_cliente(): void
    {
        Mail::fake();
        $this->entrarComo('admin@demo.test');
        $contrato = $this->contrato();
        $pago = $this->pagar($contrato, 1000000);

        $this->assertSame('paz-y-salvo-contrato-' . $contrato->numero_formateado . '.pdf', PdfRecibo::nombreEstadoCuenta($contrato->refresh()));
        $html = view('pdf.estado-cuenta', [
            'contrato' => $contrato->load('pagos'), 'pagos' => $contrato->pagos, 'pagado' => 1000000.0, 'saldo' => 0.0, 'pazYSalvo' => true,
            'ultimoPago' => $pago, 'empresa' => $this->demo, 'logo' => null, 'firma' => null, 'letras' => 'UN MILLÓN DE PESOS M/CTE',
            'v' => \App\Support\Contratos\VariablesContrato::valores($contrato, $this->demo),
        ])->render();
        $this->assertStringContainsString('A PAZ Y SALVO', $html);

        $this->assertThrows(fn () => app(EnviarRecibo::class)->recibo($pago, null, null), ValidationException::class);
        $canales = app(EnviarRecibo::class)->recibo($pago, 'cliente@ejemplo.test', null);
        $this->assertCount(1, $canales);
        app(EnviarRecibo::class)->estadoCuenta($contrato, 'cliente@ejemplo.test');

        Mail::assertSent(CorreoReciboPago::class, 2);
        Mail::assertSent(CorreoReciboPago::class, fn (CorreoReciboPago $m) => $m->tipo === CorreoReciboPago::ESTADO_CUENTA && $m->titulo === 'Paz y salvo');
        $this->assertSame(2, EnvioMensaje::query()->deEmpresa($this->demo)->whereIn('tipo', ['recibo_pago', 'estado_cuenta'])->count());
    }

    public function test_registrar_pago_desde_el_contrato_y_enviar_el_recibo(): void
    {
        Mail::fake();
        $this->entrarComo('admin@demo.test');
        $contrato = $this->contrato(800000);

        Livewire::test(PagosRelationManager::class, ['ownerRecord' => $contrato, 'pageClass' => VerContrato::class])
            ->callTableAction('create', data: [
                'fecha' => today()->toDateString(),
                'valor' => 300000,
                'metodo' => MetodoPago::Transferencia->value,
                'referencia' => 'TRF-1',
                'enviar' => true,
                'email' => 'cliente@ejemplo.test',
                'celular' => null,
            ])
            ->assertHasNoTableActionErrors()
            ->assertNotified();

        $pago = $contrato->pagos()->firstOrFail();
        $this->assertSame('TRF-1', $pago->referencia);
        $this->assertNotNull($pago->numero);
        Mail::assertSent(CorreoReciboPago::class, fn (CorreoReciboPago $m) => $m->tipo === CorreoReciboPago::RECIBO);

        Livewire::test(PagosRelationManager::class, ['ownerRecord' => $contrato, 'pageClass' => VerContrato::class])
            ->callTableAction('anular', $pago, ['motivo' => 'Transferencia devuelta'])
            ->assertHasNoTableActionErrors();
        $this->assertTrue($pago->refresh()->anulado());

        $this->get("/app/cootrasec-demo/contratos/{$contrato->id}")->assertOk()->assertSee('Estado de cuenta');
    }
}
