<?php

namespace Tests\Feature\Carga;

use App\Enums\ModuloEmpresa;
use App\Models\Conductor;
use App\Models\CumplidoManifiesto;
use App\Models\ManifiestoCarga;
use App\Models\Municipio;
use App\Models\Propietario;
use App\Models\RemesaCarga;
use App\Models\TerceroCarga;
use App\Models\TipoIdentificacion;
use App\Models\Vehiculo;
use App\Services\Rndc\ClienteRndc;
use App\Services\Rndc\Rndc;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

/**
 * Anulación ante el RNDC: remesa (proceso 9) y manifiesto (proceso 32).
 *
 * Reglas del manual que se comprueban aquí: solo se anula una remesa que no esté en un
 * manifiesto (si lo está, se libera para transbordo), y solo se anula un manifiesto que
 * no esté cumplido ni tenga remesas cumplidas.
 */
class AnulacionesRndcTest extends ConfiguracionTestCase
{
    private function empresaConectada(): void
    {
        $this->demo->update([
            'modulos' => [...ModuloEmpresa::porDefecto(), 'carga'],
            'rndc_conectado' => true,
            'rndc_usuario' => 'USUARIO1',
            'rndc_clave' => 'CLAVE1',
            'rndc_ambiente' => 'pruebas',
        ]);
        $this->entrarComo('admin@demo.test');
    }

    private function remesa(): RemesaCarga
    {
        $cc = TipoIdentificacion::where('codigo', 'CC')->value('id');
        $n = (string) (RemesaCarga::query()->withoutGlobalScopes()->count() + 1);

        $remitente = TerceroCarga::create(['empresa_id' => $this->demo->id, 'tipo_identificacion_id' => $cc,
            'documento' => '8100'.$n, 'nombre' => 'Remitente', 'primer_apellido' => 'Uno', 'celular' => '3001234567',
            'direccion' => 'Calle 1', 'municipio_id' => Municipio::where('codigo_dane', '23001')->value('id')]);
        $destinatario = TerceroCarga::create(['empresa_id' => $this->demo->id, 'tipo_identificacion_id' => $cc,
            'documento' => '8200'.$n, 'nombre' => 'Destinatario', 'primer_apellido' => 'Dos', 'celular' => '3001234567',
            'direccion' => 'Calle 2', 'municipio_id' => Municipio::where('codigo_dane', '11001')->value('id')]);

        return RemesaCarga::create(['empresa_id' => $this->demo->id, 'fecha' => today(), 'operacion' => 'G',
            'empaque_codigo' => '6', 'naturaleza' => 1, 'producto_codigo' => '001504', 'producto_descripcion' => 'Arroz',
            'cantidad' => 9000, 'unidad' => 1, 'remitente_id' => $remitente->id, 'destinatario_id' => $destinatario->id,
            'dueno_poliza' => 'N']);
    }

    private function manifiesto(RemesaCarga ...$remesas): ManifiestoCarga
    {
        $vehiculo = Vehiculo::query()->deEmpresa($this->demo)->where('placa', 'DMO101')->first();
        $vehiculo->update(['rndc_configuracion' => 50, 'peso_vacio' => 4000]);

        $manifiesto = ManifiestoCarga::create([
            'empresa_id' => $this->demo->id, 'tipo' => 'G', 'fecha_expedicion' => today(),
            'origen_id' => Municipio::where('codigo_dane', '76001')->value('id'),
            'destino_id' => Municipio::where('codigo_dane', '11001')->value('id'),
            'vehiculo_id' => $vehiculo->id,
            'conductor_id' => Conductor::query()->deEmpresa($this->demo)->where('documento', '20000001')->value('id'),
            'titular_propietario_id' => Propietario::query()->deEmpresa($this->demo)->value('id'),
            'valor_flete' => 2000000, 'anticipo' => 0, 'responsable_cargue' => 'E', 'responsable_descargue' => 'E',
        ]);

        foreach ($remesas as $r) {
            $r->update(['manifiesto_id' => $manifiesto->id]);
        }

        return $manifiesto->fresh();
    }

    /** Marca el documento como ya radicado, que es el requisito para anularlo en el RNDC. */
    private function radicar(ManifiestoCarga|RemesaCarga $documento): void
    {
        $documento->registrosRndc()->create([
            'empresa_id' => $this->demo->id, 'proceso' => $documento->procesoRndc(),
            'estado' => 'registrado', 'ingresoid' => '12345', 'ambiente' => 'pruebas',
        ]);
    }

    public function test_anular_manifiesto_envia_el_proceso_32(): void
    {
        $this->empresaConectada();
        $manifiesto = $this->manifiesto($this->remesa());
        $this->radicar($manifiesto);

        Http::fake(['*' => Http::response('<root><ingresoid>987</ingresoid></root>')]);

        $registro = app(Rndc::class)->anular($manifiesto, ['motivo' => 'S']);

        $this->assertSame(32, $registro->proceso);
        $this->assertSame('registrado', $registro->estado);
        $this->assertSame('987', $registro->ingresoid);
        $this->assertSame('S', $registro->variables['MOTIVOANULACIONMANIFIESTO']);
        $this->assertArrayHasKey('NUMMANIFIESTOCARGA', $registro->variables);
    }

    public function test_el_rndc_no_permite_anular_un_manifiesto_con_remesa_cumplida(): void
    {
        $this->empresaConectada();
        $remesa = $this->remesa();
        $manifiesto = $this->manifiesto($remesa);
        $this->radicar($manifiesto);

        $remesa->cumplido()->create(['empresa_id' => $this->demo->id, 'tipo' => 'C',
            'cantidad_entregada' => 9000, 'llegada_descargue' => now()]);

        $errores = $manifiesto->pendientesAnulacionRndc([]);

        $this->assertNotEmpty($errores);
        $this->assertStringContainsString('cumplida', implode(' ', $errores));
    }

    public function test_el_rndc_no_permite_anular_un_manifiesto_cumplido(): void
    {
        $this->empresaConectada();
        $manifiesto = $this->manifiesto($this->remesa());
        $this->radicar($manifiesto);

        CumplidoManifiesto::create(['empresa_id' => $this->demo->id, 'manifiesto_id' => $manifiesto->id,
            'tipo' => 'C', 'fecha_entrega_documentos' => today()]);

        $this->assertStringContainsString('cumplido', implode(' ', $manifiesto->fresh()->pendientesAnulacionRndc([])));
    }

    public function test_anular_remesa_suelta_envia_el_proceso_9(): void
    {
        $this->empresaConectada();
        $remesa = $this->remesa();
        $this->radicar($remesa);

        Http::fake(['*' => Http::response('<root><ingresoid>555</ingresoid></root>')]);

        $registro = app(Rndc::class)->anular($remesa, ['reversa' => 'A', 'motivo' => 'D']);

        $this->assertSame(9, $registro->proceso);
        $this->assertSame('A', $registro->variables['MOTIVOREVERSAREMESA']);
        $this->assertSame('D', $registro->variables['MOTIVOANULACIONREMESA']);
        $this->assertArrayNotHasKey('CODMUNICIPIOTRANSBORDO', $registro->variables);
    }

    public function test_una_remesa_dentro_de_un_manifiesto_no_se_anula_sino_que_se_libera(): void
    {
        $this->empresaConectada();
        $remesa = $this->remesa();
        $this->manifiesto($remesa);
        $this->radicar($remesa);

        $errores = $remesa->fresh()->pendientesAnulacionRndc(['reversa' => 'A']);

        $this->assertStringContainsString('liberarla', implode(' ', $errores));
    }

    public function test_liberar_para_transbordo_envia_municipio_y_causa(): void
    {
        $this->empresaConectada();
        $remesa = $this->remesa();
        $this->manifiesto($remesa);
        $this->radicar($remesa);

        Http::fake(['*' => Http::response('<root><ingresoid>556</ingresoid></root>')]);

        $ibague = Municipio::where('codigo_dane', '73001')->value('id');
        $registro = app(Rndc::class)->anular($remesa->fresh(), [
            'reversa' => 'L', 'motivo' => 'S',
            'municipio_transbordo_id' => $ibague, 'motivo_transbordo' => 'A',
        ]);

        $this->assertSame('L', $registro->variables['MOTIVOREVERSAREMESA']);
        $this->assertSame('A', $registro->variables['MOTIVOTRANSBORDOREMESA']);
        $this->assertSame('73001000', $registro->variables['CODMUNICIPIOTRANSBORDO']);
    }

    public function test_sin_radicar_no_se_envia_nada_al_rndc(): void
    {
        $this->empresaConectada();
        $remesa = $this->remesa();

        $this->assertStringContainsString('no está radicada', implode(' ', $remesa->pendientesAnulacionRndc(['reversa' => 'A'])));
    }

    public function test_sin_conexion_activa_la_anulacion_no_se_intenta(): void
    {
        $this->empresaConectada();
        $this->demo->update(['rndc_conectado' => false]);
        $remesa = $this->remesa();
        $this->radicar($remesa);

        $this->expectException(ValidationException::class);

        app(Rndc::class)->anular($remesa, ['reversa' => 'A', 'motivo' => 'D'], null, new ClienteRndc($this->demo->fresh()));
    }
}
