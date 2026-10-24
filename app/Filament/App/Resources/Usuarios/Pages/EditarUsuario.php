<?php

namespace App\Filament\App\Resources\Usuarios\Pages;

use App\Actions\Configuracion\AsignarRol;
use App\Filament\App\Resources\Usuarios\UsuarioResource;
use App\Models\Empresa;
use App\Models\User;
use Filament\Facades\Filament;
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

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Empresa $empresa */
        $empresa = Filament::getTenant();
        $acceso = Arr::only($data, ['rol_id', 'oficina_id', 'acceso_activo']);
        $datos = Arr::except($data, ['rol_id', 'oficina_id', 'acceso_activo']);

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
