<?php

namespace Tests\Feature\Alertas;

use App\Enums\ModuloEmpresa;
use App\Enums\TipoDocumentoVehiculo;
use App\Mail\CorreoAlertas;
use App\Models\CuotaAdministracion;
use App\Models\Propietario;
use App\Models\Vehiculo;
use App\Support\Alertas\CentroAlertas;
use App\Support\Modulos;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

/**
 * Centro de alertas: agregación de lo que requiere atención (documentos por vencer/vencidos y
 * administración en mora) y los avisos por correo al propietario/conductor (semanal y puntual).
 */
class CentroAlertasTest extends ConfiguracionTestCase
{
    private function activarAdministracion(): void
    {
        $modulos = array_values(array_unique([...ModuloEmpresa::porDefecto(), ModuloEmpresa::Administracion->value]));
        $this->demo->forceFill(['modulos' => $modulos])->save();
        Modulos::olvidar($this->demo->id);
    }

    private function propietarioConVehiculoPendiente(): Propietario
    {
        // DMO103 tiene SOAT vencido (escenario 'vencido' del seeder). Le ponemos correo al dueño.
        $vehiculo = Vehiculo::query()->deEmpresa($this->demo)->where('placa', 'DMO103')->firstOrFail();
        $propietario = $vehiculo->propietario;
        $propietario->forceFill(['email' => 'dueno103@demo.test'])->save();

        return $propietario;
    }

    public function test_el_agregador_cuenta_documentos_en_atencion_y_mora(): void
    {
        $this->activarAdministracion();
        $this->entrarComo('admin@demo.test');

        // El seeder deja al menos un documento vencido (DMO103) y uno por vencer (DMO102).
        $this->assertGreaterThanOrEqual(1, CentroAlertas::documentosVehiculo($this->demo)->count());

        // Una cuota de administración en mora (mes anterior con saldo).
        $vehiculo = Vehiculo::query()->deEmpresa($this->demo)->where('placa', 'DMO101')->firstOrFail();
        $cuota = new CuotaAdministracion([
            'vehiculo_id' => $vehiculo->id,
            'propietario_id' => $vehiculo->propietario_id,
            'periodo' => now()->subMonth()->startOfMonth(),
            'valor' => 100000,
            'pagado' => 0,
        ]);
        $cuota->empresa_id = $this->demo->id;
        $cuota->save();

        $this->assertSame(1, CentroAlertas::cuotasEnMora($this->demo)->count());

        $grupos = collect(CentroAlertas::grupos($this->demo, $this->usuario('admin@demo.test')))->keyBy('clave');
        $this->assertTrue($grupos->has('docs_vehiculo'));
        $this->assertTrue($grupos->has('administracion_mora'));
        $this->assertSame(1, $grupos->get('administracion_mora')['total']);
    }

    public function test_sin_el_modulo_no_aparece_la_mora(): void
    {
        $this->entrarComo('admin@demo.test');

        $vehiculo = Vehiculo::query()->deEmpresa($this->demo)->where('placa', 'DMO101')->firstOrFail();
        $cuota = new CuotaAdministracion([
            'vehiculo_id' => $vehiculo->id,
            'periodo' => now()->subMonth()->startOfMonth(),
            'valor' => 100000,
            'pagado' => 0,
        ]);
        $cuota->empresa_id = $this->demo->id;
        $cuota->save();

        $grupos = collect(CentroAlertas::grupos($this->demo, $this->usuario('admin@demo.test')))->keyBy('clave');
        $this->assertFalse($grupos->has('administracion_mora'));
    }

    public function test_el_resumen_semanal_envia_correo_al_propietario_con_pendientes(): void
    {
        Mail::fake();
        $propietario = $this->propietarioConVehiculoPendiente();

        $this->artisan('ruta:alertas --modo=semanal')->assertSuccessful();

        Mail::assertSent(CorreoAlertas::class, fn (CorreoAlertas $correo) => $correo->hasTo('dueno103@demo.test')
            && collect($correo->items)->isNotEmpty());
        $this->assertNotNull($propietario);
    }

    public function test_el_propietario_sin_correo_no_recibe_nada(): void
    {
        Mail::fake();
        // Nadie tiene correo (los propietarios demo no traen email).
        $this->artisan('ruta:alertas --modo=semanal')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_el_aviso_diario_solo_incluye_lo_que_vence_hoy(): void
    {
        Mail::fake();
        $propietario = $this->propietarioConVehiculoPendiente();
        $vehiculo = Vehiculo::query()->deEmpresa($this->demo)->where('placa', 'DMO103')->firstOrFail();

        // Un documento que vence HOY (queda como el vigente de su tipo por ser el más nuevo).
        $vehiculo->documentos()->create([
            'empresa_id' => $this->demo->id,
            'tipo' => TipoDocumentoVehiculo::Soat,
            'numero' => 'SOA-HOY',
            'entidad' => 'Aseguradora demo',
            'fecha_expedicion' => today()->subYear(),
            'fecha_vencimiento' => today(),
        ]);

        $this->artisan('ruta:alertas --modo=diario')->assertSuccessful();

        Mail::assertSent(CorreoAlertas::class, function (CorreoAlertas $correo) {
            // Solo el que vence hoy: todos los items deben decir "Vence hoy".
            return $correo->hasTo('dueno103@demo.test')
                && collect($correo->items)->every(fn (array $i) => str_contains($i['detalle'], 'Vence hoy'));
        });
        $this->assertNotNull($propietario);
    }
}
