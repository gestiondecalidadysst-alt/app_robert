<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;

/** Historial de avisos enviados por webhook (n8n). */
class WebhookEntrega extends Model
{
    use PerteneceAEmpresa;

    protected $table = 'webhook_entregas';

    protected $fillable = ['empresa_id', 'evento', 'payload', 'estado'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'enviado_at' => 'datetime'];
    }
}
