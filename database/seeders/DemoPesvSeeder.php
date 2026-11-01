<?php

namespace Database\Seeders;

use App\Actions\Pesv\RegistrarInspeccion;
use App\Enums\TipoInspeccion;
use App\Models\Conductor;
use App\Models\Empresa;
use App\Models\ItemChequeo;
use App\Models\User;
use App\Models\Vehiculo;
use App\Support\Pesv\ItemsBase;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Demo del PESV: el conductor 20000001 con acceso al portal (documento 20000001, clave Ruta2026*),
 * su firma registrada y preoperacionales de los días anteriores (uno no apto) e inspección mensual.
 */
class DemoPesvSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::firstWhere('slug', 'cootrasec-demo');
        if (! $empresa) {
            return;
        }

        ItemsBase::crear($empresa);

        $conductor = Conductor::query()->deEmpresa($empresa)->firstWhere('documento', '20000001');
        $vehiculo = Vehiculo::query()->deEmpresa($empresa)->firstWhere('placa', 'DMO101');
        if (! $conductor || ! $vehiculo) {
            return;
        }

        if (! $conductor->user_id) {
            $usuario = User::create([
                'name' => $conductor->nombre_completo,
                'documento' => $conductor->documento,
                'password' => DemoSeeder::CLAVE,
                'debe_cambiar_clave' => false,
            ]);
            $conductor->forceFill(['user_id' => $usuario->id])->save();
        }

        if (! $conductor->tieneFirma()) {
            $ruta = "conductores/{$empresa->id}/{$conductor->id}/firma-demo.png";
            Storage::disk('local')->put($ruta, self::garabato());
            $conductor->forceFill(['firma_path' => $ruta, 'firma_actualizada_at' => now()])->save();
        }

        if ($vehiculo->inspecciones()->exists()) {
            return;
        }

        $registrar = app(RegistrarInspeccion::class);
        $usuario = $conductor->usuario;
        $preop = ItemChequeo::query()->deEmpresa($empresa)->deTipo(TipoInspeccion::Preoperacional)->activos()->ordenados()->get();

        foreach ([6, 5, 4, 3, 2, 1] as $i => $diasAtras) {
            $respuestas = $preop->mapWithKeys(fn (ItemChequeo $item) => [$item->id => ['respuesta' => 'si']])->all();
            if ($diasAtras === 4) {
                $item = $preop->firstWhere('critico', true);
                $respuestas[$item->id] = ['respuesta' => 'no', 'observacion' => 'Nivel por debajo del mínimo, revisar posible fuga.'];
            }
            if ($diasAtras === 2) {
                $item = $preop->firstWhere('critico', false);
                $respuestas[$item->id] = ['respuesta' => 'na'];
            }

            $registrar($empresa, TipoInspeccion::Preoperacional, $vehiculo, $respuestas, conductor: $conductor,
                kilometraje: 125000 + $i * 180, contexto: ['ip' => '127.0.0.1', 'user_agent' => 'Demo'], usuario: $usuario,
                fecha: today()->subDays($diasAtras));
        }

        $mensual = ItemChequeo::query()->deEmpresa($empresa)->deTipo(TipoInspeccion::Mensual)->activos()->get()
            ->mapWithKeys(fn (ItemChequeo $item) => [$item->id => ['respuesta' => 'si']])->all();
        $registrar($empresa, TipoInspeccion::Mensual, $vehiculo, $mensual, conductor: $conductor, kilometraje: 124800,
            observaciones: 'Inspección mensual sin novedades.', usuario: User::firstWhere('email', 'admin@demo.test'),
            fecha: today()->subDays(7));
    }

    private static function garabato(): string
    {
        $img = imagecreatetruecolor(420, 140);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 255, 255, 255, 127));
        $tinta = imagecolorallocate($img, 15, 23, 42);
        imagesetthickness($img, 3);
        $x = 25; $y = 70;
        foreach ([[55, 110], [85, 30], [115, 100], [150, 40], [185, 95], [230, 55], [270, 90], [320, 40], [390, 75]] as [$nx, $ny]) {
            imageline($img, $x, $y, $nx, $ny, $tinta);
            [$x, $y] = [$nx, $ny];
        }
        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        return $png;
    }
}
