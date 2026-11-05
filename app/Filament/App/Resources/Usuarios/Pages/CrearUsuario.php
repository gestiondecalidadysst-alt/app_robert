<?php

namespace App\Filament\App\Resources\Usuarios\Pages;

use App\Actions\Configuracion\AsignarRol;
use App\Actions\Pesv\CrearAccesoConductor;
use App\Filament\App\Resources\Usuarios\UsuarioResource;
use App\Models\Conductor;
use App\Models\Empresa;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CrearUsuario extends CreateRecord
{
    protected static string $resource = UsuarioResource::class;

    /** Clave temporal del conductor, para mostrarla una sola vez. */
    private ?string $claveConductor = null;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var Empresa $empresa */
        $empresa = Filament::getTenant();

        if (UsuarioResource::esRolConductor($data['rol_id'] ?? null)) {
            return $this->crearConductor($empresa, $data);
        }

        $acceso = Arr::only($data, ['rol_id', 'oficina_id', 'acceso_activo']);
        $datos = Arr::except($data, ['rol_id', 'oficina_id', 'acceso_activo', 'conductor_id']);

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

    /**
     * Usuario del portal del conductor ligado al conductor elegido: entra con su documento y una
     * clave temporal (la escrita o una generada) que cambia al entrar.
     */
    private function crearConductor(Empresa $empresa, array $data): User
    {
        $conductor = Conductor::query()->findOrFail($data['conductor_id'] ?? null);
        $email = filled($data['email'] ?? null) ? trim($data['email']) : null;

        // Ojo: where('id', '!=', null) nunca es verdadero en SQL; por eso se filtra solo si ya tiene usuario.
        // Sin filtros: dentro del panel, User::query() solo ve los usuarios de la empresa activa.
        if ($email && User::query()->withoutGlobalScopes()->where('email', $email)
            ->when($conductor->user_id, fn ($q, $id) => $q->whereKeyNot($id))->exists()) {
            throw ValidationException::withMessages(['data.email' => 'Ese correo ya lo usa otro usuario. Déjelo vacío: el conductor entra con su documento.']);
        }

        return DB::transaction(function () use ($empresa, $conductor, $data, $email): User {
            try {
                $resultado = app(CrearAccesoConductor::class)($conductor, $data['password'] ?? null);
            } catch (ValidationException $e) {
                throw ValidationException::withMessages(['data.conductor_id' => collect($e->errors())->flatten()->first()]);
            }

            $usuario = $resultado['usuario'];
            $usuario->forceFill(array_filter([
                'name' => $data['name'] ?? null,
                'telefono' => $data['telefono'] ?? null,
                'avatar_path' => $data['avatar_path'] ?? null,
                'email' => $email,
            ], fn ($v) => filled($v)))->save();

            $empresa->usuarios()->updateExistingPivot($usuario->getKey(), [
                'oficina_id' => $data['oficina_id'] ?? $conductor->oficina_id,
                'activo' => (bool) ($data['acceso_activo'] ?? true),
            ]);
            (new AsignarRol)($usuario, $empresa, (int) $data['rol_id']);

            $this->claveConductor = $resultado['clave'];

            return $usuario;
        });
    }

    protected function getCreatedNotification(): ?Notification
    {
        if (! $this->claveConductor) {
            return parent::getCreatedNotification();
        }

        return Notification::make()
            ->success()
            ->title('Acceso del conductor creado · clave temporal: '.$this->claveConductor)
            ->body('Entréguesela al conductor (se muestra una sola vez). Ingresa en '.route('conductor.ingresar')
                .' con su documento '.$this->getRecord()->documento.' y debe cambiarla al entrar.')
            ->persistent();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
