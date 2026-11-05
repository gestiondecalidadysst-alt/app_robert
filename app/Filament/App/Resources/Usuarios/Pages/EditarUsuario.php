<?php

namespace App\Filament\App\Resources\Usuarios\Pages;

use App\Actions\Configuracion\AsignarRol;
use App\Filament\App\Resources\Usuarios\UsuarioResource;
use App\Models\Conductor;
use App\Models\Empresa;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class EditarUsuario extends EditRecord
{
    protected static string $resource = UsuarioResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Empresa $empresa */
        $empresa = Filament::getTenant();
        /** @var User $usuario */
        $usuario = $this->getRecord();
        $pivot = $usuario->empresas()->whereKey($empresa->getKey())->first()?->pivot;

        $data['rol_id'] = AsignarRol::rolActual($usuario, $empresa)?->getKey();
        $data['oficina_id'] = $pivot?->oficina_id;
        $data['acceso_activo'] = (bool) ($pivot?->activo ?? true);
        $data['conductor_id'] = Conductor::query()->where('user_id', $usuario->getKey())->value('id');

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Empresa $empresa */
        $empresa = Filament::getTenant();
        $acceso = Arr::only($data, ['rol_id', 'oficina_id', 'acceso_activo']);
        $datos = Arr::except($data, ['rol_id', 'oficina_id', 'acceso_activo', 'conductor_id']);

        // Conductor: la clave nueva es temporal (la cambia al entrar al portal).
        if (UsuarioResource::esRolConductor($acceso['rol_id'] ?? null) && filled($datos['password'] ?? null)) {
            $datos['debe_cambiar_clave'] = true;
        }
        if (blank($datos['email'] ?? null)) {
            $datos['email'] = null;
        }

        // La contraseña es global, no de esta empresa: cambiársela a alguien que también
        // trabaja en otra empresa (o a un superadministrador) sería entregar esa otra
        // cuenta. Solo el administrador de la plataforma puede hacerlo.
        if (filled($datos['password'] ?? null) && ! auth()->user()?->es_superadmin) {
            $compartido = $record->es_superadmin
                || $record->empresas()->withoutGlobalScopes()->whereKeyNot($empresa->getKey())->exists();

            if ($compartido) {
                unset($datos['password'], $datos['debe_cambiar_clave']);

                Notification::make()->warning()
                    ->title('No se cambió la contraseña')
                    ->body('Esta persona también tiene acceso en otra empresa de la plataforma, así que su contraseña solo la puede cambiar ella misma o el administrador de la plataforma. El resto de los cambios sí se guardó.')
                    ->persistent()->send();
            }
        }

        // Nadie se quita a sí mismo el acceso.
        $activo = $record->is(auth()->user()) ? true : (bool) ($acceso['acceso_activo'] ?? true);

        return DB::transaction(function () use ($record, $empresa, $acceso, $datos, $activo): Model {
            $record->update($datos);

            $empresa->usuarios()->updateExistingPivot($record->getKey(), [
                'oficina_id' => $acceso['oficina_id'] ?? null,
                'activo' => $activo,
            ]);

            (new AsignarRol)($record, $empresa, (int) $acceso['rol_id']);

            return $record;
        });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
