<?php

namespace App\Filament\App\Resources\Usuarios\Pages;

use App\Actions\Configuracion\AsignarRol;
use App\Filament\App\Resources\Usuarios\UsuarioResource;
use App\Models\Empresa;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CrearUsuario extends CreateRecord
{
    protected static string $resource = UsuarioResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var Empresa $empresa */
        $empresa = Filament::getTenant();
        $acceso = Arr::only($data, ['rol_id', 'oficina_id', 'acceso_activo']);
        $datos = Arr::except($data, ['rol_id', 'oficina_id', 'acceso_activo']);

        return DB::transaction(function () use ($empresa, $acceso, $datos): User {
            // Si el correo ya existe en la plataforma se vincula el mismo usuario.
            $usuario = User::query()->withoutGlobalScopes()->firstWhere('email', $datos['email']);

            if (! $usuario) {
                $usuario = User::query()->withoutGlobalScopes()->getModel()->newInstance($datos);
                $usuario->save();
            }

            $empresa->usuarios()->syncWithoutDetaching([
                $usuario->getKey() => [
                    'oficina_id' => $acceso['oficina_id'] ?? null,
                    'activo' => (bool) ($acceso['acceso_activo'] ?? true),
                ],
            ]);

            (new AsignarRol)($usuario, $empresa, (int) $acceso['rol_id']);

            return $usuario;
        });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
