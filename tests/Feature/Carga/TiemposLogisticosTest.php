<?php

namespace Tests\Feature\Carga;

use App\Enums\ModuloEmpresa;
use App\Models\Conductor;
use App\Models\ManifiestoCarga;
use App\Models\Municipio;
use App\Models\Propietario;
use App\Models\RemesaCarga;
use App\Models\TerceroCarga;
use App\Models\TipoIdentificacion;
use App\Models\User;
use App\Models\Vehiculo;
use App\Support\Carga\TiemposLogisticos;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

/**
 * Tiempos logísticos: llegada, entrada y salida en cargue y descargue.
 *
 * El Ministerio los exige en tiempo real y los tiempos de espera son uno de los
 * motivos por los que la Supertransporte sanciona, así que importa tanto que se
 * puedan marcar desde el celular como que el excedente sobre lo pactado se vea.
 */
class TiemposLogisticosTest extends ConfiguracionTestCase
{
    private function prepararEmpresa(): void
    {
        $this->demo->update(['modulos' => [...ModuloEmpresa::porDefecto(), 'carga']]);
    }

    private function remesaEnManifiesto(): RemesaCarga
    {
        $cc = TipoIdentificacion::where('codigo', 'CC')->value('id');

        $remitente = TerceroCarga::create(['empresa_id' => $this->demo->id, 'tipo_identificacion_id' => $cc,
            'documento' => '7700111', 'nombre' => 'Remitente', 'primer_apellido' => 'Uno', 'celular' => '3001234567',
            'direccion' => 'Calle 1', 'municipio_id' => Municipio::where('codigo_dane', '23001')->value('id')]);
        $destinatario = TerceroCarga::create(['empresa_id' => $this->demo->id, 'tipo_identificacion_id' => $cc,
            'documento' => '7700222', 'nombre' => 'Destinatario', 'primer_apellido' => 'Dos', 'celular' => '3001234567',
            'direccion' => 'Calle 2', 'municipio_id' => Municipio::where('codigo_dane', '11001')->value('id')]);

        $remesa = RemesaCarga::create(['empresa_id' => $this->demo->id, 'fecha' => today(), 'operacion' => 'G',
            'empaque_codigo' => '6', 'naturaleza' => 1, 'producto_codigo' => '001504', 'producto_descripcion' => 'Maíz',
            'cantidad' => 9000, 'unidad' => 1, 'remitente_id' => $remitente->id, 'destinatario_id' => $destinatario->id,
            'dueno_poliza' => 'N', 'horas_pacto_cargue' => 2, 'minutos_pacto_cargue' => 0,
            'horas_pacto_descargue' => 2, 'minutos_pacto_descargue' => 0]);

        $vehiculo = Vehiculo::query()->deEmpresa($this->demo)->where('placa', 'DMO101')->first();
        $conductor = Conductor::query()->deEmpresa($this->demo)->where('documento', '20000001')->first();

        $manifiesto = ManifiestoCarga::create([
            'empresa_id' => $this->demo->id, 'tipo' => 'G', 'fecha_expedicion' => today(),
            'origen_id' => Municipio::where('codigo_dane', '76001')->value('id'),
            'destino_id' => Municipio::where('codigo_dane', '11001')->value('id'),
            'vehiculo_id' => $vehiculo->id, 'conductor_id' => $conductor->id,
            'titular_propietario_id' => Propietario::query()->deEmpresa($this->demo)->value('id'),
            'valor_flete' => 2000000, 'anticipo' => 0, 'responsable_cargue' => 'E', 'responsable_descargue' => 'E',
        ]);

        $remesa->update(['manifiesto_id' => $manifiesto->id]);

        return $remesa->fresh();
    }

    public function test_los_eventos_se_marcan_en_orden(): void
    {
        $this->prepararEmpresa();
        $remesa = $this->remesaEnManifiesto();

        $this->assertSame(['cargue', 'llegada'], TiemposLogisticos::siguiente($remesa));

        TiemposLogisticos::marcar($remesa, 'cargue', 'llegada');
        $this->assertSame(['cargue', 'entrada'], TiemposLogisticos::siguiente($remesa->fresh()));
    }

    public function test_no_se_puede_salir_sin_haber_entrado(): void
    {
        $this->prepararEmpresa();
        $remesa = $this->remesaEnManifiesto();
        TiemposLogisticos::marcar($remesa, 'cargue', 'llegada');

        $this->expectException(ValidationException::class);
        TiemposLogisticos::marcar($remesa->fresh(), 'cargue', 'salida');
    }

    public function test_el_descargue_va_despues_del_cargue(): void
    {
        $this->prepararEmpresa();
        $remesa = $this->remesaEnManifiesto();

        $this->expectException(ValidationException::class);
        TiemposLogisticos::marcar($remesa, 'descargue', 'llegada');
    }

    public function test_la_espera_y_el_excedente_sobre_lo_pactado(): void
    {
        $this->prepararEmpresa();
        $remesa = $this->remesaEnManifiesto();

        TiemposLogisticos::marcar($remesa, 'cargue', 'llegada', datos: ['ocurrido_at' => now()->setTime(8, 0)]);
        TiemposLogisticos::marcar($remesa->fresh(), 'cargue', 'entrada', datos: ['ocurrido_at' => now()->setTime(9, 0)]);
        TiemposLogisticos::marcar($remesa->fresh(), 'cargue', 'salida', datos: ['ocurrido_at' => now()->setTime(13, 30)]);

        $remesa = $remesa->fresh();

        // 8:00 a 13:30 son 5,5 h; lo pactado eran 2 h.
        $this->assertSame(5.5, TiemposLogisticos::espera($remesa, 'cargue'));
        $this->assertSame(2.0, TiemposLogisticos::pactadas($remesa, 'cargue'));
        $this->assertSame(3.5, TiemposLogisticos::excedente($remesa, 'cargue'));
    }

    public function test_los_tiempos_alimentan_las_horas_del_cumplido(): void
    {
        $this->prepararEmpresa();
        $remesa = $this->remesaEnManifiesto();

        TiemposLogisticos::marcar($remesa, 'cargue', 'llegada', datos: ['ocurrido_at' => now()->setTime(7, 0)]);
        TiemposLogisticos::marcar($remesa->fresh(), 'cargue', 'entrada', datos: ['ocurrido_at' => now()->setTime(7, 30)]);
        TiemposLogisticos::marcar($remesa->fresh(), 'cargue', 'salida', datos: ['ocurrido_at' => now()->setTime(9, 0)]);

        $remesa = $remesa->fresh();

        $this->assertSame('07:30', $remesa->entrada_cargue?->format('H:i'));
        $this->assertSame('09:00', $remesa->salida_cargue?->format('H:i'));
    }

    public function test_un_evento_no_se_marca_dos_veces(): void
    {
        $this->prepararEmpresa();
        $remesa = $this->remesaEnManifiesto();
        TiemposLogisticos::marcar($remesa, 'cargue', 'llegada');

        $this->expectException(ValidationException::class);
        TiemposLogisticos::marcar($remesa->fresh(), 'cargue', 'llegada');
    }

    public function test_una_remesa_anulada_no_admite_tiempos(): void
    {
        $this->prepararEmpresa();
        $remesa = $this->remesaEnManifiesto();
        $remesa->update(['anulado_at' => now()]);

        $this->expectException(ValidationException::class);
        TiemposLogisticos::marcar($remesa->fresh(), 'cargue', 'llegada');
    }

    public function test_el_conductor_ve_sus_manifiestos_y_marca_desde_el_portal(): void
    {
        $this->prepararEmpresa();
        $remesa = $this->remesaEnManifiesto();
        $conductor = Conductor::query()->deEmpresa($this->demo)->where('documento', '20000001')->first();

        $this->actingAs($this->usuarioDelConductor($conductor));

        $this->get(route('conductor.carga'))
            ->assertOk()
            ->assertSee($remesa->numeroFormateado())
            ->assertSee('Reportar llegada en cargue');

        $this->post(route('conductor.carga.marcar', $remesa), [
            'punto' => 'cargue', 'evento' => 'llegada',
            'latitud' => 4.6097100, 'longitud' => -74.0817500,
        ])->assertRedirect();

        $tiempo = $remesa->fresh()->tiemposLogisticos()->firstOrFail();

        $this->assertSame('cargue', $tiempo->punto);
        $this->assertSame('llegada', $tiempo->evento);
        $this->assertSame($conductor->id, $tiempo->conductor_id);
        $this->assertTrue($tiempo->tieneUbicacion());
    }

    public function test_un_conductor_no_marca_tiempos_de_un_manifiesto_ajeno(): void
    {
        $this->prepararEmpresa();
        $remesa = $this->remesaEnManifiesto();
        // Un segundo conductor de la misma empresa, ajeno a este manifiesto.
        $propio = Conductor::query()->deEmpresa($this->demo)->where('documento', '20000001')->first();
        $ajeno = $propio->replicate(['documento', 'user_id']);
        $ajeno->documento = '29999999';
        $ajeno->user_id = $this->usuario('auxiliar@demo.test')->id;
        $ajeno->save();

        $this->actingAs($this->usuarioDelConductor($ajeno));

        $this->post(route('conductor.carga.marcar', $remesa), ['punto' => 'cargue', 'evento' => 'llegada'])
            ->assertForbidden();
    }

    private function usuarioDelConductor(Conductor $conductor): User
    {
        return User::query()->withoutGlobalScopes()->findOrFail($conductor->user_id);
    }
}
