<?php

namespace Database\Seeders;

use App\Actions\Operacion\CrearContrato;
use App\Actions\Operacion\ExpedirFuec;
use App\Actions\Operacion\RegistrarPago;
use App\Enums\FormaPago;
use App\Enums\MetodoPago;
use App\Enums\ObjetoContrato;
use App\Models\Cliente;
use App\Models\Conductor;
use App\Models\Contrato;
use App\Models\Empresa;
use App\Models\Municipio;
use App\Models\TipoIdentificacion;
use App\Models\Vehiculo;
use Illuminate\Database\Seeder;

/**
 * Clientes, contratos, pagos y un FUEC de demostración para la empresa demo.
 * Completa con valores ficticios los datos de habilitación que exige el número del FUEC.
 */
class DemoOperacionSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::firstWhere('slug', 'cootrasec-demo');
        if (! $empresa || Contrato::query()->deEmpresa($empresa)->exists()) {
            return;
        }

        $empresa->forceFill([
            'codigo_territorial' => $empresa->codigo_territorial ?: '423',
            'resolucion_habilitacion' => $empresa->resolucion_habilitacion ?: '0123',
            'anio_habilitacion' => $empresa->anio_habilitacion ?: 2015,
            'representante_legal' => $empresa->representante_legal ?: 'Gerente Demo',
        ])->save();

        $nit = TipoIdentificacion::firstWhere('codigo', 'NIT')?->id;
        $monteria = Municipio::firstWhere('codigo_dane', '23001')?->id;
        $cerete = Municipio::where('nombre', 'like', 'CERET%')->value('id') ?? $monteria;
        $cartagena = Municipio::firstWhere('codigo_dane', '13001')?->id ?? $monteria;

        $crear = fn (array $datos) => tap(new Cliente($datos), function (Cliente $c) use ($empresa) {
            $c->empresa_id = $empresa->id;
            $c->save();
        });

        $colegio = $crear(['tipo_identificacion_id' => $nit, 'documento' => '800111222', 'nombre' => 'Colegio Demo del Sinú', 'municipio_id' => $monteria, 'telefono' => '6047000000', 'contacto' => 'Rectoría']);
        $agroindustria = $crear(['tipo_identificacion_id' => $nit, 'documento' => '900333444', 'nombre' => 'Agroindustrial Demo S.A.S.', 'municipio_id' => $monteria, 'telefono' => '6047111111', 'contacto' => 'Talento humano']);

        $escolar = app(CrearContrato::class)($empresa, [
            'cliente_id' => $colegio->id,
            'objeto' => ObjetoContrato::Escolar,
            'descripcion_objeto' => 'Transporte de estudiantes del Colegio Demo del Sinú, rutas de la mañana y la tarde.',
            'fecha_inicio' => today()->startOfMonth()->toDateString(),
            'fecha_fin' => today()->addMonths(2)->endOfMonth()->toDateString(),
            'origen_municipio_id' => $monteria,
            'destino_municipio_id' => $monteria,
            'recorrido' => 'Barrios del sur – sede principal del colegio.',
            'cantidad_vehiculos' => 2,
            'cantidad_pasajeros' => 50,
            'valor' => 18000000,
            'forma_pago' => FormaPago::Credito,
            'fecha_limite_pago' => today()->addMonth()->toDateString(),
            'responsable_nombre' => 'Coordinación de transporte',
        ]);

        $empresarial = app(CrearContrato::class)($empresa, [
            'cliente_id' => $agroindustria->id,
            'objeto' => ObjetoContrato::Empresarial,
            'descripcion_objeto' => 'Traslado de personal a la planta.',
            'fecha_inicio' => today()->toDateString(),
            'fecha_fin' => today()->addDays(10)->toDateString(),
            'origen_municipio_id' => $monteria,
            'destino_municipio_id' => $cerete,
            'valor' => 4500000,
            'forma_pago' => FormaPago::Contado,
        ]);

        app(RegistrarPago::class)($escolar, ['fecha' => today()->toDateString(), 'valor' => 6000000, 'metodo' => MetodoPago::Transferencia, 'referencia' => 'TRF-DEMO-1']);
        app(RegistrarPago::class)($empresarial, ['fecha' => today()->toDateString(), 'valor' => 4500000, 'metodo' => MetodoPago::Efectivo]);

        $vehiculo = Vehiculo::query()->deEmpresa($empresa)->firstWhere('placa', 'DMO101');
        $conductor = Conductor::query()->deEmpresa($empresa)->firstWhere('documento', '20000001');

        if ($vehiculo && $conductor) {
            $expedir = app(ExpedirFuec::class);
            $datos = [
                'contrato_id' => $empresarial->id,
                'vehiculo_id' => $vehiculo->id,
                'conductores' => [$conductor->id],
                'fecha_inicio' => today()->toDateString(),
                'fecha_fin' => today()->addDays(10)->toDateString(),
                'origen_municipio_id' => $monteria,
                'destino_municipio_id' => $cerete,
                'recorrido' => 'Montería – Cereté por la vía principal.',
            ];

            if ($expedir->validar($empresa, $datos) === []) {
                $expedir($empresa, $datos);
            }
        }
    }
}
