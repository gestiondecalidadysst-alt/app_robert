<?php

namespace Tests\Feature\Administracion;

use App\Actions\Administracion\AnularPagoAdministracion;
use App\Actions\Administracion\RegistrarPagoAdministracion;
use App\Enums\ModuloEmpresa;
use App\Filament\App\Resources\EstadoAdministracion\Pages\ListEstadoAdministracion;
use App\Models\CuotaAdministracion;
use App\Models\Vehiculo;
use App\Support\Modulos;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

/**
 * Pagos de administración: generación mensual de cuentas de cobro por vehículo, pago repartido de la
 * cuota más vieja a la más nueva (FIFO), anulación que devuelve el abono y descarga del recibo.
 */
class PagosAdministracionTest extends ConfiguracionTestCase
{
    private function activarModulo(): void
    {
        $modulos = array_values(array_unique([...ModuloEmpresa::porDefecto(), ModuloEmpresa::Administracion->value]));
        $this->demo->forceFill(['modulos' => $modulos])->save();
        Modulos::olvidar($this->demo->id);
    }

    private function vehiculoDemo(): Vehiculo
    {
        return Vehiculo::query()->deEmpresa($this->demo)->enFlota()->firstOrFail();
    }

    private function cuota(Vehiculo $vehiculo, string $periodo, int $valor, int $pagado = 0): CuotaAdministracion
    {
        $cuota = new CuotaAdministracion([
            'vehiculo_id' => $vehiculo->id,
            'propietario_id' => $vehiculo->propietario_id,
            'clase_vehiculo_id' => $vehiculo->clase_vehiculo_id,
            'periodo' => Carbon::parse($periodo)->startOfMonth(),
            'valor' => $valor,
            'pagado' => $pagado,
        ]);
        $cuota->empresa_id = $this->demo->id;
        $cuota->save();

        return $cuota;
    }

    public function test_la_generacion_crea_una_cuota_del_mes_por_vehiculo_y_no_duplica(): void
    {
        $this->activarModulo();
        $vehiculo = $this->vehiculoDemo();
        $vehiculo->forceFill(['administracion_valor' => 150000])->save();

        $this->artisan('ruta:generar-cuotas-administracion')->assertSuccessful();

        $cuota = CuotaAdministracion::query()->deEmpresa($this->demo)
            ->where('vehiculo_id', $vehiculo->id)
            ->whereDate('periodo', now()->startOfMonth()->toDateString())
            ->first();
        $this->assertNotNull($cuota);
        $this->assertSame(150000, (int) $cuota->valor);

        // Idempotente: una segunda corrida no duplica la cuota del mismo mes.
        $this->artisan('ruta:generar-cuotas-administracion')->assertSuccessful();
        $this->assertSame(1, CuotaAdministracion::query()->deEmpresa($this->demo)
            ->where('vehiculo_id', $vehiculo->id)
            ->whereDate('periodo', now()->startOfMonth()->toDateString())->count());
    }

    public function test_el_boton_del_estado_de_cuenta_genera_las_cuotas_del_mes(): void
    {
        $this->activarModulo();
        $vehiculo = $this->vehiculoDemo();
        $vehiculo->forceFill(['administracion_valor' => 180000])->save();

        $this->entrarComo('admin@demo.test');

        Livewire::test(ListEstadoAdministracion::class)
            ->callAction('generar');

        $cuota = CuotaAdministracion::query()->deEmpresa($this->demo)
            ->where('vehiculo_id', $vehiculo->id)
            ->whereDate('periodo', now()->startOfMonth()->toDateString())
            ->first();
        $this->assertNotNull($cuota);
        $this->assertSame(180000, (int) $cuota->valor);

        // Repetir el botón no duplica.
        Livewire::test(ListEstadoAdministracion::class)->callAction('generar');
        $this->assertSame(1, CuotaAdministracion::query()->deEmpresa($this->demo)
            ->where('vehiculo_id', $vehiculo->id)
            ->whereDate('periodo', now()->startOfMonth()->toDateString())->count());
    }

    public function test_sin_el_modulo_activo_no_genera_cuotas(): void
    {
        // El módulo no está en los de por defecto, así que la demo no lo tiene.
        $vehiculo = $this->vehiculoDemo();
        $vehiculo->forceFill(['administracion_valor' => 150000])->save();

        $this->artisan('ruta:generar-cuotas-administracion')->assertSuccessful();

        $this->assertSame(0, CuotaAdministracion::query()->deEmpresa($this->demo)->count());
    }

    public function test_el_pago_se_reparte_de_la_cuota_mas_vieja_a_la_mas_nueva(): void
    {
        $this->activarModulo();
        $vehiculo = $this->vehiculoDemo();
        $ene = $this->cuota($vehiculo, '2026-01-01', 100000);
        $feb = $this->cuota($vehiculo, '2026-02-01', 100000);
        $mar = $this->cuota($vehiculo, '2026-03-01', 100000);

        $pago = app(RegistrarPagoAdministracion::class)($vehiculo, [
            'fecha' => now(), 'valor' => 250000, 'metodo' => 'efectivo', 'user_id' => $this->usuario('admin@demo.test')->id,
        ]);

        $this->assertSame(250000, (int) $pago->valor);
        $this->assertSame(100000, $ene->refresh()->pagado);
        $this->assertSame(100000, $feb->refresh()->pagado);
        $this->assertSame(50000, $mar->refresh()->pagado);
        $this->assertSame('pagada', $ene->estado());
        $this->assertSame('parcial', $mar->estado());
        // El reparto quedó registrado en el pivote para poder revertirlo.
        $this->assertSame(3, $pago->cuotas()->count());
    }

    public function test_no_admite_pago_mayor_que_lo_pendiente(): void
    {
        $this->activarModulo();
        $vehiculo = $this->vehiculoDemo();
        $this->cuota($vehiculo, '2026-01-01', 100000);

        $this->expectException(ValidationException::class);
        app(RegistrarPagoAdministracion::class)($vehiculo, ['fecha' => now(), 'valor' => 150000]);
    }

    public function test_anular_el_recibo_devuelve_el_abono_a_las_cuotas(): void
    {
        $this->activarModulo();
        $vehiculo = $this->vehiculoDemo();
        $ene = $this->cuota($vehiculo, '2026-01-01', 100000);
        $feb = $this->cuota($vehiculo, '2026-02-01', 100000);

        $pago = app(RegistrarPagoAdministracion::class)($vehiculo, ['fecha' => now(), 'valor' => 150000]);
        $this->assertSame(100000, $ene->refresh()->pagado);
        $this->assertSame(50000, $feb->refresh()->pagado);

        app(AnularPagoAdministracion::class)($pago, 'Pago reversado', $this->usuario('admin@demo.test')->id);

        $this->assertTrue($pago->refresh()->anulado());
        $this->assertSame(0, $ene->refresh()->pagado);
        $this->assertSame(0, $feb->refresh()->pagado);
        $this->assertSame('pendiente', $ene->estado());
    }

    public function test_el_recibo_se_descarga_para_un_usuario_de_la_empresa_y_con_permiso(): void
    {
        $this->activarModulo();
        $vehiculo = $this->vehiculoDemo();
        $this->cuota($vehiculo, '2026-01-01', 100000);
        $pago = app(RegistrarPagoAdministracion::class)($vehiculo, ['fecha' => now(), 'valor' => 100000]);

        $this->actingAs($this->usuario('admin@demo.test'));
        $this->get(route('administracion.recibo', $pago))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_un_usuario_de_otra_empresa_no_descarga_el_recibo(): void
    {
        $this->activarModulo();
        $vehiculo = $this->vehiculoDemo();
        $this->cuota($vehiculo, '2026-01-01', 100000);
        $pago = app(RegistrarPagoAdministracion::class)($vehiculo, ['fecha' => now(), 'valor' => 100000]);

        $this->actingAs($this->usuario('admin@ejemplo.test'));
        $this->get(route('administracion.recibo', $pago))->assertForbidden();
    }
}
