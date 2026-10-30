<?php

namespace Database\Seeders;

use App\Actions\Conductores\AsignarVehiculo;
use App\Actions\Conductores\CambiarEstadoConductor;
use App\Enums\EstadoComparendos;
use App\Enums\TipoDocumentoConductor;
use App\Enums\TipoNovedadConductor;
use App\Models\Arl;
use App\Models\CategoriaLicencia;
use App\Models\Conductor;
use App\Models\Empresa;
use App\Models\Eps;
use App\Models\Municipio;
use App\Models\TipoIdentificacion;
use App\Models\Vehiculo;
use Illuminate\Database\Seeder;

/**
 * Conductores ficticios para la empresa demo, con un caso de cada situación
 * (al día, licencia por vencer, seguridad social vencida, licencia particular, suspendido)
 * y asignados a los vehículos demo. Fechas calculadas desde hoy.
 */
class DemoConductoresSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::firstWhere('slug', 'cootrasec-demo');
        if (! $empresa || Conductor::query()->deEmpresa($empresa)->exists()) {
            return;
        }

        $cc = TipoIdentificacion::firstWhere('codigo', 'CC')?->id;
        $monteria = Municipio::firstWhere('codigo_dane', '23001')?->id;
        $eps = Eps::query()->orderBy('nombre')->value('id');
        $arl = Arl::query()->orderBy('nombre')->value('id');
        $categoria = fn (string $codigo) => CategoriaLicencia::firstWhere('codigo', $codigo)?->id;
        $vehiculo = fn (string $placa) => Vehiculo::query()->deEmpresa($empresa)->firstWhere('placa', $placa);

        // documento, nombres, apellidos, categoría, escenario, placa asignada
        $conductores = [
            ['20000001', 'Conductor', 'Uno Demo', 'C2', 'al_dia', 'DMO101'],
            ['20000002', 'Conductora', 'Dos Demo', 'C2', 'licencia_por_vencer', 'DMO102'],
            ['20000003', 'Conductor', 'Tres Demo', 'C1', 'seguridad_vencida', 'DMO103'],
            ['20000004', 'Conductor', 'Cuatro Demo', 'B1', 'particular', 'DMO105'],
            ['20000005', 'Conductora', 'Cinco Demo', 'C3', 'suspendido', 'DMO104'],
        ];

        foreach ($conductores as $i => [$documento, $nombres, $apellidos, $cat, $escenario, $placa]) {
            $conductor = new Conductor([
                'tipo_identificacion_id' => $cc,
                'documento' => $documento,
                'nombres' => $nombres,
                'apellidos' => $apellidos,
                'fecha_nacimiento' => now()->subYears(30 + $i * 4)->toDateString(),
                'telefono' => '310000000' . $i,
                'municipio_id' => $monteria,
                'grupo_sanguineo' => 'O+',
                'eps_id' => $eps,
                'arl_id' => $arl,
                'fecha_ingreso' => '2023-02-01',
                'comparendos_estado' => $i === 2 ? EstadoComparendos::AcuerdoPago : EstadoComparendos::SinComparendos,
                'comparendos_cantidad' => $i === 2 ? 1 : 0,
                'comparendos_consultado_el' => today()->subDays(10)->toDateString(),
            ]);
            $conductor->empresa_id = $empresa->id;
            $conductor->save();

            $documentos = [
                [TipoDocumentoConductor::Licencia, $escenario === 'licencia_por_vencer' ? today()->addDays(20) : today()->addYears(2), $categoria($cat)],
                [TipoDocumentoConductor::SeguridadSocial, $escenario === 'seguridad_vencida' ? today()->subDays(3) : today()->addDays(25), null],
                [TipoDocumentoConductor::ExamenMedico, today()->addMonths(5), null],
            ];

            foreach ($documentos as [$tipo, $vence, $categoriaId]) {
                $conductor->documentos()->create([
                    'empresa_id' => $empresa->id,
                    'tipo' => $tipo,
                    'numero' => sprintf('%s-%s', strtoupper(substr($tipo->value, 0, 3)), $documento),
                    'categoria_licencia_id' => $categoriaId,
                    'entidad' => 'Entidad demo',
                    'fecha_expedicion' => $vence->copy()->subMonths($tipo->mesesVigencia())->addDay(),
                    'fecha_vencimiento' => $vence,
                ]);
            }

            if ($v = $vehiculo($placa)) {
                app(AsignarVehiculo::class)($conductor, $v, today()->subMonths(3), principal: true);
            }

            if ($escenario === 'suspendido') {
                app(CambiarEstadoConductor::class)(
                    $conductor,
                    TipoNovedadConductor::Suspension,
                    'Suspensión temporal (dato de demostración).',
                    today()->subDay(),
                    today()->addDays(10),
                );
            }
        }
    }
}
