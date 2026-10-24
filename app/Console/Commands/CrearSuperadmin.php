<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Crea (o actualiza) el superadministrador de la plataforma.
 *
 *   php artisan ruta:superadmin                 -> pregunta los datos
 *   php artisan ruta:superadmin --si-no-existe  -> usa SUPERADMIN_EMAIL / SUPERADMIN_PASSWORD / SUPERADMIN_NAME
 *                                                  y solo actúa si todavía no hay ningún superadministrador
 */
class CrearSuperadmin extends Command
{
    protected $signature = 'ruta:superadmin {--si-no-existe : Solo si aún no existe ninguno; toma los datos de las variables de entorno}';

    protected $description = 'Crea el superadministrador de la plataforma';

    public function handle(): int
    {
        if ($this->option('si-no-existe')) {
            if (User::where('es_superadmin', true)->exists()) {
                $this->line('Ya existe un superadministrador; no se hace nada.');

                return self::SUCCESS;
            }

            $datos = config('ruta.superadmin');

            if (blank($datos['email']) || blank($datos['password'])) {
                $this->warn('No hay superadministrador. Defina SUPERADMIN_EMAIL y SUPERADMIN_PASSWORD para crearlo.');

                return self::SUCCESS;
            }
        } else {
            $datos = [
                'name' => $this->ask('Nombre completo', 'Superadministrador'),
                'email' => $this->ask('Correo electrónico'),
                'password' => $this->secret('Contraseña (mínimo 8 caracteres)'),
            ];
        }

        if (strlen((string) $datos['password']) < 8) {
            $this->error('La contraseña debe tener al menos 8 caracteres.');

            return self::FAILURE;
        }

        User::updateOrCreate(['email' => $datos['email']], [
            'name' => $datos['name'] ?: 'Superadministrador',
            'password' => $datos['password'],
            'es_superadmin' => true,
            'activo' => true,
        ]);

        $this->info("Superadministrador listo: {$datos['email']}");

        return self::SUCCESS;
    }
}
