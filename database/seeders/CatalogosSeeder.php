<?php

namespace Database\Seeders;

use App\Models\Arl;
use App\Models\Aseguradora;
use App\Models\Carroceria;
use App\Models\CategoriaLicencia;
use App\Models\ClaseVehiculo;
use App\Models\Combustible;
use App\Models\Departamento;
use App\Models\Eps;
use App\Models\Linea;
use App\Models\Marca;
use App\Models\Municipio;
use App\Models\NivelAcademico;
use App\Models\TipoIdentificacion;
use App\Models\TipoServicio;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Catálogos nacionales. Es idempotente: se puede ejecutar varias veces.
 * Municipios: DIVIPOLA (DANE), tomado del paquete jamesmosq/municipios_colombia (licencia MIT).
 */
class CatalogosSeeder extends Seeder
{
    public function run(): void
    {
        // La carga inicial de catálogos no se registra en auditoría.
        activity()->disableLogging();

        $this->municipios();

        foreach ([
            ['CC', 'Cédula de ciudadanía', false],
            ['CE', 'Cédula de extranjería', false],
            ['NIT', 'NIT', true],
            ['PA', 'Pasaporte', false],
            ['TI', 'Tarjeta de identidad', false],
            ['PEP', 'Permiso especial de permanencia', false],
            ['PPT', 'Permiso por protección temporal', false],
        ] as [$codigo, $nombre, $esEmpresa]) {
            TipoIdentificacion::updateOrCreate(['codigo' => $codigo], ['nombre' => $nombre, 'es_empresa' => $esEmpresa]);
        }

        $this->simples(ClaseVehiculo::class, ['Automóvil', 'Bus', 'Buseta', 'Microbús', 'Camioneta', 'Campero', 'Van', 'Camión']);
        $this->simples(Carroceria::class, ['Cerrada', 'Sedán', 'Van', 'Wagon', 'Doble cabina', 'Cabinado', 'Pick-up']);
        $this->simples(Combustible::class, ['Diésel (ACPM)', 'Gasolina', 'Gas natural', 'Eléctrico', 'Híbrido']);
        $this->simples(TipoServicio::class, ['Especial', 'Público', 'Particular', 'Oficial']);
        $this->simples(NivelAcademico::class, ['Primaria', 'Bachillerato', 'Técnico', 'Tecnólogo', 'Profesional', 'Posgrado']);

        foreach ([
            ['A1', 'Motocicletas hasta 125 c.c.'],
            ['A2', 'Motocicletas de más de 125 c.c.'],
            ['B1', 'Automóviles, camionetas y microbuses (servicio particular)'],
            ['B2', 'Camiones rígidos, busetas y buses (servicio particular)'],
            ['B3', 'Vehículos articulados (servicio particular)'],
            ['C1', 'Automóviles, camionetas y microbuses (servicio público)'],
            ['C2', 'Camiones rígidos, busetas y buses (servicio público)'],
            ['C3', 'Vehículos articulados (servicio público)'],
        ] as [$codigo, $descripcion]) {
            CategoriaLicencia::updateOrCreate(['codigo' => $codigo], ['descripcion' => $descripcion]);
        }

        $this->simples(Eps::class, [
            'Nueva EPS', 'EPS Sura', 'EPS Sanitas', 'Salud Total', 'Coosalud', 'Famisanar', 'Compensar',
            'Mutual Ser', 'SOS EPS', 'Aliansalud', 'Emssanar', 'Asmet Salud', 'Cajacopi', 'Savia Salud',
            'Capresoca', 'Comfenalco Valle', 'Pijaos Salud', 'Dusakawi', 'Anas Wayuu', 'Mallamas',
        ]);
        $this->simples(Arl::class, [
            'ARL Sura', 'Positiva Compañía de Seguros', 'Colmena Seguros', 'Seguros Bolívar',
            'AXA Colpatria', 'La Equidad Seguros', 'Seguros de Vida Alfa',
        ]);
        $this->simples(Aseguradora::class, [
            'Seguros del Estado', 'Seguros Sura', 'La Previsora', 'Seguros Mundial', 'AXA Colpatria',
            'Seguros Bolívar', 'Allianz Seguros', 'HDI Seguros', 'Aseguradora Solidaria', 'La Equidad Seguros', 'Mapfre Seguros',
        ]);

        foreach ([
            'Chevrolet' => ['NPR', 'NKR', 'NHR', 'NQR', 'FRR', 'Spark', 'Captiva'],
            'Hyundai' => ['County', 'H-1', 'Solati', 'Tucson'],
            'Toyota' => ['Hiace', 'Hilux', 'Prado', 'Land Cruiser', 'Fortuner'],
            'Mercedes-Benz' => ['Sprinter', 'LO 915', 'OF 1721'],
            'Renault' => ['Master', 'Duster', 'Logan'],
            'Nissan' => ['Urvan', 'Frontier', 'NV350'],
            'Ford' => ['Transit', 'Ranger'],
            'Volkswagen' => ['Crafter', 'Amarok'],
            'Kia' => ['Sportage', 'Sorento'],
            'JAC' => ['Sunray', 'HFC1061'],
            'Foton' => ['View CS2', 'Aumark'],
            'Agrale' => ['MA 8.7', 'MA 10.0'],
            'BYD' => ['C6', 'T3'],
            'Hino' => ['Dutro', 'FC'],
            'International' => ['3300'],
        ] as $marca => $lineas) {
            $modelo = Marca::updateOrCreate(['nombre' => $marca]);
            foreach ($lineas as $linea) {
                Linea::updateOrCreate(['marca_id' => $modelo->id, 'nombre' => $linea]);
            }
        }

        activity()->enableLogging();
    }

    private function simples(string $modelo, array $nombres): void
    {
        foreach ($nombres as $nombre) {
            $modelo::updateOrCreate(['nombre' => $nombre]);
        }
    }

    private function municipios(): void
    {
        $archivo = database_path('data/municipios_dane.csv');
        $filas = array_map('str_getcsv', file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        array_shift($filas); // encabezado

        DB::transaction(function () use ($filas): void {
            $departamentos = [];
            foreach ($filas as [$codigo, $codigoDepartamento, $departamento, $municipio]) {
                $departamentos[$codigoDepartamento] ??= Departamento::updateOrCreate(
                    ['codigo_dane' => $codigoDepartamento],
                    ['nombre' => $departamento],
                )->id;

                Municipio::updateOrCreate(
                    ['codigo_dane' => $codigo],
                    ['nombre' => $municipio, 'departamento_id' => $departamentos[$codigoDepartamento]],
                );
            }
        });
    }
}
