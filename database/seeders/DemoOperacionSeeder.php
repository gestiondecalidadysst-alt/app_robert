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
            'codigo_territorial' => $empresa->codigo_territorial ?: '223', // Córdoba - Sucre
            'resolucion_habilitacion' => $empresa->resolucion_habilitacion ?: '0123',
            'anio_habilitacion' => $empresa->anio_habilitacion ?: 2015,
            'representante_legal' => $empresa->representante_legal ?: 'Gerente Demo',
            'representante_documento' => $empresa->representante_documento ?: '10000000',
            'representante_expedicion' => $empresa->representante_expedicion ?: 'Montería',
            'sitio_web' => $empresa->sitio_web ?: 'www.demo.test',
            'firma_representante_path' => $empresa->firma_representante_path ?: self::firmaDemo($empresa->id),
        ])->save();

        $nit = TipoIdentificacion::firstWhere('codigo', 'NIT')?->id;
        $monteria = Municipio::firstWhere('codigo_dane', '23001')?->id;
        $cerete = Municipio::where('nombre', 'like', 'CERET%')->value('id') ?? $monteria;
        $cartagena = Municipio::firstWhere('codigo_dane', '13001')?->id ?? $monteria;

        $crear = fn (array $datos) => tap(new Cliente($datos), function (Cliente $c) use ($empresa) {
            $c->empresa_id = $empresa->id;
            $c->save();
        });

        $colegio = $crear(['tipo_identificacion_id' => $nit, 'documento' => '800111222', 'nombre' => 'Colegio Demo del Sinú', 'municipio_id' => $monteria, 'telefono' => '6047000000', 'contacto' => 'Rectoría', 'direccion' => 'Calle 10 # 5-20']);
        $agroindustria = $crear(['tipo_identificacion_id' => $nit, 'documento' => '900333444', 'nombre' => 'Agroindustrial Demo S.A.S.', 'municipio_id' => $monteria, 'telefono' => '3001112233', 'email' => 'talento@agro.test', 'contacto' => 'Jefe de talento humano', 'direccion' => 'Km 5 vía Cereté']);

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
            'hora_inicio' => '06:00',
            'hora_fin' => '15:00',
            'cantidad_pasajeros' => 19,
            'recorrido' => 'Montería – Cereté por la vía principal.',
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
                'hora_inicio' => '06:00',
                'hora_fin' => '15:00',
                'origen_municipio_id' => $monteria,
                'destino_municipio_id' => $cerete,
                'recorrido' => 'Montería – Cereté por la vía principal.',
            ];

            if ($expedir->validar($empresa, $datos) === []) {
                $expedir($empresa, $datos);
            }
        }

        // Plantilla con el código del formato y el contrato empresarial emitido (listo para enviar a firma).
        $base = \App\Models\PlantillaContrato::query()->deEmpresa($empresa)->orderByDesc('version')->first();
        if ($base && ! $base->codigo_formato) {
            app(\App\Actions\Contratos\GuardarPlantillaContrato::class)($empresa, [
                'version' => 2,
                'codigo_formato' => 'GPS-PR01-FT03',
                'titulo' => $base->titulo,
                'contenido' => $base->contenido,
                'notas_cambio' => 'Código del formato del sistema de gestión (ejemplo).',
            ]);
        }

        app(\App\Actions\Contratos\EmitirDocumentoContrato::class)($empresarial->refresh());
    }

    /** Firma ficticia del representante (garabato) para la empresa demo. */
    private static function firmaDemo(int $empresaId): string
    {
        $img = imagecreatetruecolor(420, 140);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 255, 255, 255, 127));
        $tinta = imagecolorallocate($img, 20, 30, 70);
        imagesetthickness($img, 3);
        $x = 20; $y = 90;
        foreach ([[60, 40], [90, 100], [130, 30], [170, 95], [210, 50], [250, 85], [300, 45], [360, 70], [400, 60]] as [$nx, $ny]) {
            imageline($img, $x, $y, $nx, $ny, $tinta);
            [$x, $y] = [$nx, $ny];
        }
        imageline($img, 40, 115, 380, 108, $tinta);
        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        $ruta = "firmas-representante/{$empresaId}/firma-demo.png";
        \Illuminate\Support\Facades\Storage::disk('local')->put($ruta, $png);

        return $ruta;
    }
}
