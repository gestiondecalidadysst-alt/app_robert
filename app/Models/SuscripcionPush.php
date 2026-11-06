<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Suscripción de un navegador a las notificaciones Web Push. Pertenece a un usuario; un usuario
 * puede tener varias (varios dispositivos). La unicidad es por endpoint (vía endpoint_hash).
 */
class SuscripcionPush extends Model
{
    protected $table = 'suscripciones_push';

    protected $fillable = ['user_id', 'empresa_id', 'endpoint', 'endpoint_hash', 'p256dh', 'auth', 'user_agent'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Registra o actualiza la suscripción de un navegador (idempotente por endpoint). */
    public static function registrar(User $usuario, array $datos, ?int $empresaId = null): self
    {
        return static::updateOrCreate(
            ['endpoint_hash' => hash('sha256', $datos['endpoint'])],
            [
                'user_id' => $usuario->id,
                'empresa_id' => $empresaId,
                'endpoint' => $datos['endpoint'],
                'p256dh' => $datos['p256dh'],
                'auth' => $datos['auth'],
                'user_agent' => $datos['user_agent'] ?? null,
            ],
        );
    }
}
