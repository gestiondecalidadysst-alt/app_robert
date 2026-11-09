<?php

namespace Tests\Feature\Seguridad;

use App\Models\Cliente;
use App\Models\Conductor;
use App\Models\Empresa;
use App\Models\Fuec;
use App\Models\Municipio;
use App\Models\Oficina;
use App\Models\TipoIdentificacion;
use App\Models\Vehiculo;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

/**
 * Red de seguridad del aislamiento multiempresa (trait PerteneceAEmpresa).
 *
 * Fija que el scope global por empresa activa nunca deje ver ni crear datos
 * cruzados entre empresas, para que un cambio futuro no lo rompa en silencio.
 * `demo` (cootrasec-demo) tiene flota sembrada; `otra` (transportes-ejemplo) está vacía.
 */
class AislamientoMultiempresaTest extends ConfiguracionTestCase
{
    /** Entra al panel autenticado como el admin de la empresa indicada (tenant + permisos). */
    private function enEmpresa(Empresa $empresa): void
    {
        $email = $empresa->is($this->otra) ? 'admin@ejemplo.test' : 'admin@demo.test';
        $this->entrarComo($email, $empresa);
    }

    public function test_el_scope_global_solo_muestra_filas_de_la_empresa_activa(): void
    {
        $this->enEmpresa($this->demo);

        $this->assertGreaterThan(0, Vehiculo::count(), 'La empresa demo debe tener flota sembrada.');
        $this->assertTrue(
            Vehiculo::get()->every(fn (Vehiculo $v) => $v->empresa_id === $this->demo->id),
            'Ningún vehículo visible debe pertenecer a otra empresa.',
        );

        // La otra empresa no tiene flota: no debe ver nada de demo.
        $this->enEmpresa($this->otra);
        $this->assertSame(0, Vehiculo::count());
        $this->assertSame(0, Conductor::count());
        $this->assertSame(0, Fuec::count());
    }

    public function test_find_no_cruza_empresas_y_de_empresa_hace_bypass_explicito(): void
    {
        $this->enEmpresa($this->demo);
        $vehiculo = Vehiculo::query()->firstOrFail();

        // Desde la otra empresa, el scope global oculta la fila por completo.
        $this->enEmpresa($this->otra);
        $this->assertNull(Vehiculo::find($vehiculo->id));

        // El scope explícito deEmpresa() sí la encuentra (uso fuera del panel: consola, colas).
        $this->assertNotNull(Vehiculo::deEmpresa($this->demo)->find($vehiculo->id));
        // Pero nunca devuelve filas de una empresa que no las tiene.
        $this->assertSame(0, Vehiculo::deEmpresa($this->otra)->count());
    }

    public function test_crear_autollena_la_empresa_activa_y_queda_aislado(): void
    {
        $municipio = Municipio::query()->firstOrFail();

        $this->enEmpresa($this->otra);
        $oficina = Oficina::create(['nombre' => 'Sede principal', 'municipio_id' => $municipio->id]);
        $this->assertSame($this->otra->id, $oficina->empresa_id, 'empresa_id debe autollenarse con la empresa activa.');

        // La oficina recién creada en "otra" es invisible desde demo, pero deEmpresa la encuentra.
        $this->enEmpresa($this->demo);
        $this->assertNull(Oficina::find($oficina->id));
        $this->assertNotNull(Oficina::deEmpresa($this->otra)->find($oficina->id));
    }

    public function test_un_registro_creado_en_cada_empresa_no_se_mezcla(): void
    {
        $tipo = TipoIdentificacion::query()->firstOrFail()->id;

        $this->enEmpresa($this->demo);
        $enDemo = Cliente::create([
            'tipo_identificacion_id' => $tipo, 'documento' => '900100200', 'nombre' => 'Cliente Demo',
        ]);

        $this->enEmpresa($this->otra);
        $enOtra = Cliente::create([
            'tipo_identificacion_id' => $tipo, 'documento' => '900100200', 'nombre' => 'Cliente Otra',
        ]);

        // Mismo documento en ambas empresas no colisiona: son registros distintos y aislados.
        $this->assertNotSame($enDemo->id, $enOtra->id);
        $this->assertSame($this->otra->id, $enOtra->empresa_id);
        $this->assertNull(Cliente::find($enDemo->id), 'Desde otra no se ve el cliente de demo.');

        $this->enEmpresa($this->demo);
        $this->assertNull(Cliente::find($enOtra->id), 'Desde demo no se ve el cliente de otra.');
        $this->assertNotNull(Cliente::find($enDemo->id));
    }
}
