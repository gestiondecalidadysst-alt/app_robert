<?php

namespace App\Console\Commands;

use App\Support\Push\WebPush;
use Illuminate\Console\Command;

/**
 * Genera un par de llaves VAPID para las notificaciones Web Push. Se corre una sola vez; las dos
 * líneas que imprime se pegan en las variables de entorno (Easypanel). La privada no se guarda.
 */
class GenerarVapid extends Command
{
    protected $signature = 'ruta:generar-vapid';

    protected $description = 'Genera las llaves VAPID (Web Push) para pegar en el entorno.';

    public function handle(): int
    {
        $pk = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_pkey_export($pk, $pem);
        $d = openssl_pkey_get_details($pk);
        $publica = WebPush::b64url(WebPush::puntoSinComprimir($d['ec']['x'], $d['ec']['y']));

        $this->info('Pegue estas dos variables en el entorno (Easypanel) y redespliegue:');
        $this->newLine();
        $this->line('VAPID_PUBLIC='.$publica);
        $this->line('VAPID_PRIVATE_PEM_B64='.base64_encode($pem));
        $this->newLine();
        $this->warn('Guarde la privada en lugar seguro. Si la cambia, los dispositivos ya suscritos dejan de recibir avisos.');

        return self::SUCCESS;
    }
}
