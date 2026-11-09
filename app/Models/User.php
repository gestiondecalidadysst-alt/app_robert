<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Modulos;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasDefaultTenant;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasAvatar, HasDefaultTenant, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, Notifiable;

    use HasRoles {
        checkPermissionTo as protected checkPermissionToBase;
    }

    /** Módulos contratados: un permiso de un módulo que la empresa activa no tiene se niega (ver Modulos). */
    public function checkPermissionTo($permission, ?string $guardName = null): bool
    {
        if (is_string($permission) && Modulos::permisoBloqueado($permission)) {
            return false;
        }

        return $this->checkPermissionToBase($permission, $guardName);
    }

    protected $fillable = [
        'name', 'email', 'password', 'tipo_identificacion_id', 'documento', 'telefono',
        'avatar_path', 'es_superadmin', 'activo', 'debe_cambiar_clave', 'ultima_empresa_id', 'ultimo_acceso_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'ultimo_acceso_at' => 'datetime',
            'password' => 'hashed',
            'es_superadmin' => 'boolean',
            'activo' => 'boolean',
            'debe_cambiar_clave' => 'boolean',
        ];
    }

    // Relaciones

    public function empresas(): BelongsToMany
    {
        return $this->belongsToMany(Empresa::class)
            ->using(EmpresaUsuario::class)
            ->withPivot(['oficina_id', 'activo'])
            ->withTimestamps();
    }

    /** Hojas de vida de conductor ligadas a este usuario (portal del conductor), en cualquier empresa. */
    public function conductores(): HasMany
    {
        return $this->hasMany(Conductor::class, 'user_id')->withoutGlobalScopes()->whereNull('conductores.deleted_at');
    }

    /** Suscripciones Web Push (navegadores que aceptaron recibir avisos). */
    public function suscripcionesPush(): HasMany
    {
        return $this->hasMany(SuscripcionPush::class, 'user_id');
    }

    /**
     * Empresas donde el usuario tiene el rol Conductor: solo usa el portal del conductor, no el panel.
     *
     * @return Collection<int, int>
     */
    public function empresasComoConductor(): Collection
    {
        $tabla = config('permission.table_names.model_has_roles');

        return DB::table($tabla)
            ->join('roles', 'roles.id', '=', "{$tabla}.role_id")
            ->where("{$tabla}.model_type", $this->getMorphClass())
            ->where("{$tabla}.model_id", $this->getKey())
            ->where('roles.name', Rol::CONDUCTOR)
            ->pluck("{$tabla}.empresa_id");
    }

    public function ultimaEmpresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'ultima_empresa_id');
    }

    public function tipoIdentificacion(): BelongsTo
    {
        return $this->belongsTo(TipoIdentificacion::class);
    }

    /** Empresas activas a las que el usuario tiene acceso activo. */
    public function empresasDisponibles(): Collection
    {
        if ($this->es_superadmin) {
            return Empresa::query()->activas()->orderBy('razon_social')->get();
        }

        return $this->empresas()
            ->where('empresas.activa', true)
            ->wherePivot('activo', true)
            ->whereNotIn('empresas.id', $this->empresasComoConductor())
            ->orderBy('razon_social')
            ->get();
    }

    // Filament

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->activo) {
            return false;
        }

        return match ($panel->getId()) {
            'admin' => $this->es_superadmin,
            'app' => $this->es_superadmin || $this->empresasDisponibles()->isNotEmpty(),
            default => false,
        };
    }

    public function getTenants(Panel $panel): Collection
    {
        return $this->empresasDisponibles();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $tenant instanceof Empresa
            && $this->empresasDisponibles()->contains($tenant->getKey());
    }

    public function getDefaultTenant(Panel $panel): ?Model
    {
        $disponibles = $this->empresasDisponibles();

        return $disponibles->firstWhere('id', $this->ultima_empresa_id) ?? $disponibles->first();
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    /** Oficina asignada al usuario dentro de una empresa. */
    public function oficinaEn(Empresa $empresa): ?Oficina
    {
        $pivot = $this->empresas()->whereKey($empresa->getKey())->first()?->pivot;

        return $pivot?->oficina_id ? Oficina::withoutGlobalScopes()->find($pivot->oficina_id) : null;
    }
}
