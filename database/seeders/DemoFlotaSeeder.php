<?php

namespace Database\Seeders;

use App\Actions\Flota\CambiarEstadoVehiculo;
use App\Enums\TipoDocumentoVehiculo;
use App\Enums\TipoNovedadVehiculo;
use App\Models\Aseguradora;
use App\Models\Carroceria;
use App\Models\ClaseVehiculo;
use App\Models\Combustible;
use App\Models\Empresa;
use App\Models\Linea;
use App\Models\Marca;
use App\Models\Municipio;
use App\Models\Propietario;
use App\Models\TipoIdentificacion;
use App\Models\TipoServicio;
use App\Models\Vehiculo;
use Illuminate\Database\Seeder;

/**
 * Flota de demostración para la empresa demo: propietarios ficticios y vehículos con
 * documentos en distintos estados (vigentes, por vencer, vencidos y sin registrar).
 * Las fechas se calculan desde hoy, así el semáforo siempre muestra todos los casos.
 */
class DemoFlotaSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::firstWhere('slug', 'cootrasec-demo');
        if (! $empresa || Vehiculo::query()->deEmpresa($empresa)->exists()) {
            return;
        }

        $cc = TipoIdentificacion::firstWhere('codigo', 'CC')?->id;
        $nit = TipoIdentificacion::firstWhere('codigo', 'NIT')?->id;
        $monteria = Municipio::firstWhere('codigo_dane', '23001')?->id;

        $propietarios = collect([
            [$nit, '900123456', 'Cooperativa de Transportadores Demo', null],
            [$cc, '10000001', 'Propietario', 'Uno Demo'],
            [$cc, '10000002', 'Propietaria', 'Dos Demo'],
        ])->map(fn (array $p) => Propietario::create([
            'empresa_id' => $empresa->id,
            'tipo_identificacion_id' => $p[0],
            'documento' => $p[1],
            'nombres' => $p[2],
            'apellidos' => $p[3],
            'telefono' => '3000000000',
            'municipio_id' => $monteria,
            'fecha_ingreso' => '2020-01-15',
        ]));

        $id = fn (string $modelo, string $nombre) => $modelo::firstWhere('nombre', $nombre)?->id;
        $linea = fn (string $marca, string $nombre) => Linea::query()
            ->where('marca_id', $id(Marca::class, $marca))->where('nombre', $nombre)->value('id');

        // placa, interno, propietario, marca, línea, modelo, clase, carrocería, pasajeros, escenario de documentos
        $vehiculos = [
            ['DMO101', '01', 0, 'Mercedes-Benz', 'Sprinter', 2022, 'Microbús', 'Van', 19, 'al_dia'],
            ['DMO102', '02', 1, 'Hyundai', 'County', 2019, 'Buseta', 'Cerrada', 30, 'por_vencer'],
            ['DMO103', '03', 1, 'Toyota', 'Hiace', 2021, 'Van', 'Van', 15, 'vencido'],
            ['DMO104', '04', 2, 'Chevrolet', 'NPR', 2018, 'Bus', 'Cerrada', 40, 'faltante'],
            ['DMO105', '05', 2, 'Toyota', 'Prado', 2023, 'Camioneta', 'Wagon', 7, 'al_dia'],
            ['DMO106', '06', 0, 'Hyundai', 'H-1', 2020, 'Van', 'Van', 12, 'al_dia'],
        ];

        $aseguradora = Aseguradora::query()->orderBy('nombre')->value('id');

        foreach ($vehiculos as [$placa, $interno, $prop, $marca, $lin, $modelo, $clase, $carroceria, $pasajeros, $escenario]) {
            $vehiculo = Vehiculo::create([
                'empresa_id' => $empresa->id,
                'placa' => $placa,
                'numero_interno' => $interno,
                'propietario_id' => $propietarios[$prop]->id,
                'marca_id' => $id(Marca::class, $marca),
                'linea_id' => $linea($marca, $lin),
                'modelo' => $modelo,
                'clase_vehiculo_id' => $id(ClaseVehiculo::class, $clase),
                'carroceria_id' => $id(Carroceria::class, $carroceria),
                'combustible_id' => $id(Combustible::class, 'Diésel (ACPM)'),
                'tipo_servicio_id' => $id(TipoServicio::class, 'Público'),
                'color' => 'Blanco',
                'capacidad_pasajeros' => $pasajeros,
                'organismo_transito_id' => $monteria,
                'tipo_vinculacion' => 'vinculacion_flota',
                'fecha_vinculacion' => '2024-01-10',
                'ficha_tecnica' => ['numero_ejes' => 2, 'sistema_direccion' => 'Hidráulica'],
            ]);

            foreach (TipoDocumentoVehiculo::cases() as $i => $tipo) {
                $vence = match (true) {
                    $escenario === 'faltante' && $tipo === TipoDocumentoVehiculo::TarjetaOperacion => null,
                    $escenario === 'vencido' && $tipo === TipoDocumentoVehiculo::Soat => today()->subDays(5),
                    $escenario === 'por_vencer' && $tipo === TipoDocumentoVehiculo::Tecnomecanica => today()->addDays(12),
                    $tipo === TipoDocumentoVehiculo::RevisionPreventiva => today()->addDays(40 + $i),
                    default => today()->addMonths(4 + $i),
                };

                if ($vence === null) {
                    continue;
                }

                // Renovación anterior (historial) y documento vigente.
                foreach ([$vence->copy()->subMonths($tipo->mesesVigencia() ?? 12), $vence] as $n => $fecha) {
                    $vehiculo->documentos()->create([
                        'empresa_id' => $empresa->id,
                        'tipo' => $tipo,
                        'numero' => sprintf('%s-%s-%d', strtoupper(substr($tipo->value, 0, 3)), $placa, $n + 1),
                        'aseguradora_id' => $tipo->usaAseguradora() ? $aseguradora : null,
                        'entidad' => $tipo->usaAseguradora() ? null : 'Entidad demo',
                        'fecha_expedicion' => $fecha->copy()->subMonths($tipo->mesesVigencia() ?? 12)->addDay(),
                        'fecha_vencimiento' => $fecha,
                    ]);
                }
            }

            if ($placa === 'DMO106') {
                app(CambiarEstadoVehiculo::class)(
                    $vehiculo->refresh(),
                    TipoNovedadVehiculo::Taller,
                    'Cambio de embrague (dato de demostración).',
                    today()->subDays(2),
                    today()->addDays(5),
                );
            }
        }
    }
}
