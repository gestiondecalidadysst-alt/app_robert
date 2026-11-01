<?php

namespace Tests\Feature\Operacion;

use App\Actions\Conductores\CambiarEstadoConductor;
use App\Actions\Operacion\AnularContrato;
use App\Actions\Operacion\AnularFuec;
use App\Actions\Operacion\CrearContrato;
use App\Actions\Operacion\ExpedirFuec;
use App\Actions\Operacion\RegistrarPago;
use App\Enums\EstadoVigencia;
use App\Enums\MetodoPago;
use App\Enums\ObjetoContrato;
use App\Enums\TipoNovedadConductor;
use App\Filament\App\Resources\Contratos\Pages\CrearContrato as PaginaCrearContrato;
use App\Filament\App\Resources\Fuecs\Pages\ExpedirFuec as PaginaExpedirFuec;
use App\Models\Cliente;
use App\Models\Conductor;
use App\Models\Contrato;
use App\Models\Fuec;
use App\Models\Municipio;
use App\Models\Vehiculo;
use App\Support\Pdf\PdfFuec;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

class OperacionTest extends ConfiguracionTestCase
{
    private function vehiculo(string $placa): Vehiculo
    {
        return Vehiculo::query()->deEmpresa($this->demo)->where('placa', $placa)->firstOrFail();
    }

    private function conductor(string $documento): Conductor
    {
        return Conductor::query()->deEmpresa($this->demo)->where('documento', $documento)->firstOrFail();
    }

    private function contratoVigente(): Contrato
    {
        return Contrato::query()->deEmpresa($this->demo)->where('numero', 1)->firstOrFail();
    }

    /** Datos válidos: DMO101 y "Conductor Uno Demo" están al día. */
    private function datosFuec(array $cambios = []): array
    {
        $monteria = Municipio::firstWhere('codigo_dane', '23001')->id;

        return [
            'contrato_id' => $this->contratoVigente()->id,
            'vehiculo_id' => $this->vehiculo('DMO101')->id,
            'conductores' => [$this->conductor('20000001')->id],
            'fecha_inicio' => today()->toDateString(),
            'fecha_fin' => today()->addDays(5)->toDateString(),
            // El FUEC demo de DMO101 y el conductor 20000001 es de 06:00 a 15:00: este no se cruza.
            'hora_inicio' => '16:00',
            'hora_fin' => '20:00',
            'origen_municipio_id' => $monteria,
            'destino_municipio_id' => $monteria,
            ...$cambios,
        ];
    }

    public function test_el_administrador_abre_las_pantallas_de_operacion(): void
    {
        $this->actingAs($this->usuario('admin@demo.test'));
        $fuec = Fuec::query()->deEmpresa($this->demo)->firstOrFail();
        $contrato = $this->contratoVigente();

        foreach ([
            '/app/cootrasec-demo/clientes',
            '/app/cootrasec-demo/contratos',
            '/app/cootrasec-demo/contratos/crear',
            "/app/cootrasec-demo/contratos/{$contrato->id}",
            "/app/cootrasec-demo/contratos/{$contrato->id}/editar",
            '/app/cootrasec-demo/fuec',
            "/app/cootrasec-demo/fuec/expedir?contrato={$contrato->id}",
            "/app/cootrasec-demo/fuec/{$fuec->id}",
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_permisos_de_gerencia_en_operacion(): void
    {
        $fuec = Fuec::query()->deEmpresa($this->demo)->firstOrFail();

        $this->actingAs($this->usuario('gerencia@demo.test'));
        $this->get("/app/cootrasec-demo/fuec/{$fuec->id}")->assertOk();
        $this->get('/app/cootrasec-demo/fuec/expedir')->assertForbidden();
        $this->get('/app/cootrasec-demo/contratos/crear')->assertForbidden();
    }

    public function test_no_se_ven_fuec_de_otra_empresa(): void
    {
        $fuec = Fuec::query()->deEmpresa($this->demo)->firstOrFail();

        $this->actingAs($this->usuario('admin@ejemplo.test'));
        $this->get("/app/transportes-ejemplo/fuec/{$fuec->id}")->assertNotFound();
    }

    public function test_numero_del_fuec_segun_la_resolucion_6652(): void
    {
        // Ejemplo oficial del instructivo (reverso del formato): Dirección Territorial Cundinamarca (425),
        // resolución 0155 de 2012, primer extracto de 2015 del contrato 255.
        $this->demo->forceFill(['codigo_territorial' => '425', 'resolucion_habilitacion' => '155', 'anio_habilitacion' => 2012]);
        $this->assertSame('425015512201502550001', ExpedirFuec::numero($this->demo, 2015, 255, 1));

        $this->assertSame(21, strlen(ExpedirFuec::numero($this->demo, 2026, 123456, 98765)));
        $this->assertStringEndsWith('34568765', ExpedirFuec::numero($this->demo, 2026, 123456, 98765), 'Toma los últimos 4 dígitos.');
    }

    public function test_expedir_fuec_guarda_copia_conductores_y_consecutivo_por_contrato(): void
    {
        $this->entrarComo('admin@demo.test');
        $contrato = $this->contratoVigente();

        $fuec = app(ExpedirFuec::class)($this->demo, $this->datosFuec());
        $otro = app(ExpedirFuec::class)($this->demo, $this->datosFuec(['hora_inicio' => '20:00', 'hora_fin' => '23:00', 'conductores' => [$this->conductor('20000001')->id, $this->conductor('20000002')->id]]));

        $this->assertSame(1, $fuec->consecutivo);
        $this->assertSame(2, $otro->consecutivo);
        $this->assertSame(21, strlen($fuec->numero));
        $this->assertStringEndsWith(str_pad((string) $contrato->numero, 4, '0', STR_PAD_LEFT) . '0002', $otro->numero);
        $this->assertSame('DMO101', $fuec->datos['vehiculo']['placa']);
        $this->assertSame('Conductor Uno Demo', $fuec->datos['conductores'][0]['nombre']);
        $this->assertSame([1, 2], $otro->conductores->pluck('pivot.orden')->all());
        $this->assertSame(EstadoVigencia::Vigente, $fuec->estado());

        // La copia no cambia aunque cambien los maestros.
        $this->vehiculo('DMO101')->update(['numero_interno' => '99']);
        $this->assertSame('01', $fuec->refresh()->datos['vehiculo']['numero_interno']);

        // El PDF se genera y la verificación pública responde sin sesión.
        $this->assertStringStartsWith('%PDF', PdfFuec::generar($fuec));
        auth()->logout();
        $this->get($fuec->urlVerificacion())->assertOk()->assertSee('DMO101')->assertSee('Vigente')->assertDontSee('20000001');
    }

    public function test_bloqueos_al_expedir(): void
    {
        $this->entrarComo('admin@demo.test');
        $expedir = app(ExpedirFuec::class);
        $contrato = $this->contratoVigente();

        // Vehículo con SOAT vencido y conductor con planilla vencida.
        $errores = $expedir->validar($this->demo, $this->datosFuec([
            'vehiculo_id' => $this->vehiculo('DMO103')->id,
            'conductores' => [$this->conductor('20000003')->id],
        ]));
        $this->assertStringContainsString('SOAT', implode(' ', $errores['vehiculo_id']));
        $this->assertStringContainsString('Seguridad social', implode(' ', $errores['conductores']));

        // Vigencia fuera del contrato (art. 15) y en el pasado.
        $errores = $expedir->validar($this->demo, $this->datosFuec(['fecha_fin' => $contrato->fecha_fin->copy()->addDay()->toDateString()]));
        $this->assertArrayHasKey('fecha_fin', $errores);
        $this->assertArrayHasKey('fecha_inicio', $expedir->validar($this->demo, $this->datosFuec(['fecha_inicio' => today()->subDay()->toDateString()])));

        // Conductor repetido y más de 3 conductores.
        $uno = $this->conductor('20000001')->id;
        $this->assertArrayHasKey('conductores', $expedir->validar($this->demo, $this->datosFuec(['conductores' => [$uno, $uno]])));

        // Un documento que vence durante la vigencia no bloquea el inicio, pero limita la fecha final:
        // la técnico-mecánica de DMO102 vence en 12 días.
        $dmo102 = $this->vehiculo('DMO102');
        $errores = $expedir->validar($this->demo, $this->datosFuec([
            'vehiculo_id' => $dmo102->id,
            'fecha_fin' => today()->addDays(20)->toDateString(),
        ]));
        $this->assertArrayNotHasKey('vehiculo_id', $errores);
        $this->assertStringContainsString('técnico-mecánica', $errores['fecha_fin'][0]);
        $this->assertStringContainsString('máxima posible es ' . today()->addDays(12)->format('d/m/Y'), $errores['fecha_fin'][0]);
        $this->assertSame([], $expedir->validar($this->demo, $this->datosFuec([
            'vehiculo_id' => $dmo102->id,
            'fecha_fin' => today()->addDays(12)->toDateString(),
        ])), 'Hasta el día en que vence, sí.');
        $this->assertTrue($expedir->fechaMaxima($this->demo, $this->datosFuec([
            'vehiculo_id' => $dmo102->id,
            'fecha_fin' => today()->addDays(20)->toDateString(),
        ]))->isSameDay(today()->addDays(12)));

        // Código territorial que no es de una Dirección Territorial.
        $this->demo->forceFill(['codigo_territorial' => '999'])->save();
        $this->assertStringContainsString('no es de una Dirección Territorial', implode(' ', $expedir->validar($this->demo->refresh(), $this->datosFuec())['contrato_id']));

        // Empresa sin datos de habilitación.
        $this->demo->forceFill(['codigo_territorial' => null])->save();
        $this->assertStringContainsString('Mi empresa', implode(' ', $expedir->validar($this->demo->refresh(), $this->datosFuec())['contrato_id']));

        $this->expectException(ValidationException::class);
        $expedir($this->demo, $this->datosFuec());
    }

    public function test_expedir_desde_la_pantalla(): void
    {
        $this->entrarComo('admin@demo.test');
        $contrato = $this->contratoVigente();
        $datos = $this->datosFuec();

        Livewire::withQueryParams(['contrato' => $contrato->id])
            ->test(PaginaExpedirFuec::class)
            ->assertSchemaStateSet(['contrato_id' => $contrato->id])
            ->fillForm([
                'vehiculo_id' => $datos['vehiculo_id'],
                'conductor_1' => $datos['conductores'][0],
                'fecha_fin' => $datos['fecha_fin'],
                'hora_inicio' => $datos['hora_inicio'],
                'hora_fin' => $datos['hora_fin'],
                'origen_municipio_id' => $datos['origen_municipio_id'],
                'destino_municipio_id' => $datos['destino_municipio_id'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, Fuec::query()->where('contrato_id', $contrato->id)->count());

        // Con un conductor bloqueado, el error aparece en el campo del conductor.
        Livewire::withQueryParams(['contrato' => $contrato->id])
            ->test(PaginaExpedirFuec::class)
            ->fillForm([
                'vehiculo_id' => $datos['vehiculo_id'],
                'conductor_1' => $this->conductor('20000005')->id, // suspendido
                'fecha_fin' => $datos['fecha_fin'],
                'origen_municipio_id' => $datos['origen_municipio_id'],
                'destino_municipio_id' => $datos['destino_municipio_id'],
            ])
            ->call('create')
            ->assertHasFormErrors(['conductor_1']);
    }

    public function test_contratos_consecutivo_pagos_y_anulacion(): void
    {
        $this->entrarComo('admin@demo.test');
        $cliente = Cliente::query()->firstOrFail();

        Livewire::test(PaginaCrearContrato::class)
            ->fillForm([
                'cliente_id' => $cliente->id,
                'objeto' => ObjetoContrato::Turismo->value,
                'fecha_inicio' => today()->toDateString(),
                'fecha_fin' => today()->addDays(3)->toDateString(),
                'valor' => 1000000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $nuevo = Contrato::query()->orderByDesc('id')->first();
        $this->assertSame(3, $nuevo->numero, 'Sigue al último contrato de la empresa.');
        $this->assertSame(1, app(CrearContrato::class)($this->otra, $nuevo->only(['objeto', 'fecha_inicio', 'fecha_fin', 'valor']) + ['cliente_id' => Cliente::create(['empresa_id' => $this->otra->id, 'documento' => '1', 'nombre' => 'X'])->id])->numero, 'Consecutivo independiente por empresa.');

        // Pagos y saldo.
        app(RegistrarPago::class)($nuevo, ['fecha' => today(), 'valor' => 400000, 'metodo' => MetodoPago::Efectivo]);
        $this->assertSame(600000.0, $nuevo->saldo());
        $this->assertThrows(fn () => app(RegistrarPago::class)($nuevo, ['fecha' => today(), 'valor' => 700000, 'metodo' => MetodoPago::Efectivo]), ValidationException::class);

        // No se anula un contrato con FUEC activos; anulado el FUEC, sí.
        $fuec = app(ExpedirFuec::class)($this->demo, $this->datosFuec(['contrato_id' => $nuevo->id, 'fecha_fin' => today()->addDays(3)->toDateString()]));
        $this->assertThrows(fn () => app(AnularContrato::class)($nuevo, 'Cliente canceló'), ValidationException::class);

        app(AnularFuec::class)($fuec, 'Cliente canceló');
        $this->assertSame(EstadoVigencia::Anulado, $fuec->refresh()->estado());
        app(AnularContrato::class)($nuevo->refresh(), 'Cliente canceló');
        $this->assertTrue($nuevo->refresh()->anulado());

        // Contrato anulado: no admite FUEC ni pagos.
        $this->assertArrayHasKey('contrato_id', app(ExpedirFuec::class)->validar($this->demo, $this->datosFuec(['contrato_id' => $nuevo->id])));
        $this->assertThrows(fn () => app(RegistrarPago::class)($nuevo, ['fecha' => today(), 'valor' => 1, 'metodo' => MetodoPago::Efectivo]), ValidationException::class);

        // La verificación pública muestra el FUEC anulado.
        $this->get($fuec->urlVerificacion())->assertOk()->assertSee('Anulado');
    }

    public function test_un_conductor_retirado_no_puede_ir_en_un_fuec(): void
    {
        $this->entrarComo('admin@demo.test');
        $c = $this->conductor('20000001');
        app(CambiarEstadoConductor::class)($c, TipoNovedadConductor::Retiro, 'Retiro');

        $errores = app(ExpedirFuec::class)->validar($this->demo, $this->datosFuec());
        $this->assertStringContainsString('retirado', implode(' ', $errores['conductores']));
    }

    public function test_no_se_asigna_el_mismo_vehiculo_o_conductor_a_servicios_que_se_cruzan(): void
    {
        $this->entrarComo('admin@demo.test');
        $expedir = app(ExpedirFuec::class);
        $uno = $this->conductor('20000001')->id;
        $dos = $this->conductor('20000002')->id;
        $dmo101 = $this->vehiculo('DMO101')->id;
        $dmo102 = $this->vehiculo('DMO102')->id;
        $hoy = today()->toDateString();
        $manana = today()->addDay()->toDateString();
        $pasado = today()->addDays(2)->toDateString();

        // DMO102 con la conductora 2 hoy, todo el día (00:00 a 23:59).
        $todoElDia = $expedir($this->demo, $this->datosFuec(['vehiculo_id' => $dmo102, 'fecha_fin' => $hoy, 'hora_inicio' => null, 'hora_fin' => null, 'conductores' => [$dos]]));

        // Mismo vehículo hoy a cualquier hora: bloqueado.
        $errores = $expedir->validar($this->demo, $this->datosFuec(['vehiculo_id' => $dmo102, 'fecha_fin' => $hoy, 'hora_inicio' => '21:00', 'hora_fin' => '22:00', 'conductores' => [$uno]]));
        $this->assertStringContainsString('DMO102 ya está asignado', implode(' ', $errores['vehiculo_id']));
        $this->assertStringContainsString('todo el día', implode(' ', $errores['vehiculo_id']));

        // Misma conductora con otro vehículo (DMO101 de 16:00 a 20:00, libre): bloqueada por la conductora.
        $errores = $expedir->validar($this->demo, $this->datosFuec(['fecha_fin' => $hoy, 'conductores' => [$dos]]));
        $this->assertArrayNotHasKey('vehiculo_id', $errores);
        $this->assertStringContainsString('Conductora Dos Demo ya está asignado', implode(' ', $errores['conductores']));

        // DMO101 tiene el FUEC demo de 06:00 a 15:00: a las 14:00 se cruza; desde las 15:00, no.
        $errores = $expedir->validar($this->demo, $this->datosFuec(['fecha_inicio' => $manana, 'fecha_fin' => $manana, 'hora_inicio' => '14:00', 'hora_fin' => '18:00']));
        $this->assertStringContainsString('de 06:00 a 15:00', implode(' ', $errores['vehiculo_id']));
        $this->assertArrayNotHasKey('vehiculo_id', $expedir->validar($this->demo, $this->datosFuec(['fecha_inicio' => $manana, 'fecha_fin' => $manana, 'hora_inicio' => '15:00', 'hora_fin' => '18:00'])));

        // Nocturno de mañana (22:00 a 05:00) se cruza con pasado mañana a las 04:00.
        $expedir($this->demo, $this->datosFuec(['vehiculo_id' => $dmo102, 'fecha_inicio' => $manana, 'fecha_fin' => $manana, 'hora_inicio' => '22:00', 'hora_fin' => '05:00', 'conductores' => [$dos]]));
        $errores = $expedir->validar($this->demo, $this->datosFuec(['vehiculo_id' => $dmo102, 'fecha_inicio' => $pasado, 'fecha_fin' => $pasado, 'hora_inicio' => '04:00', 'hora_fin' => '08:00', 'conductores' => [$uno]]));
        $this->assertStringContainsString('del día siguiente', implode(' ', $errores['vehiculo_id']));
        $this->assertArrayNotHasKey('vehiculo_id', $expedir->validar($this->demo, $this->datosFuec(['vehiculo_id' => $dmo102, 'fecha_inicio' => $pasado, 'fecha_fin' => $pasado, 'hora_inicio' => '05:00', 'hora_fin' => '08:00', 'conductores' => [$uno]])));

        // Un FUEC anulado libera el vehículo.
        app(AnularFuec::class)($todoElDia, 'Prueba');
        $this->assertArrayNotHasKey('vehiculo_id', $expedir->validar($this->demo, $this->datosFuec(['vehiculo_id' => $dmo102, 'fecha_fin' => $hoy, 'hora_inicio' => '21:00', 'hora_fin' => '22:00', 'conductores' => [$uno]])));
    }
}
