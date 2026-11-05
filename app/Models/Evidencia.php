<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * Archivo adjunto (factura, foto, certificado) de un mantenimiento o de un plan de acción.
 * Se guarda en el disco privado y se descarga con permiso (App\Http\Controllers\EvidenciaController).
 */
class Evidencia extends Model
{
    use PerteneceAEmpresa;

    public const DISCO = 'local';

    public const MAX_KB = 10240;

    public const TIPOS = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];

    protected $table = 'evidencias';

    protected $fillable = ['empresa_id', 'path', 'nombre', 'mime', 'tamano', 'descripcion', 'user_id'];

    public function adjuntable(): MorphTo
    {
        return $this->morphTo();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function esImagen(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    public function existe(): bool
    {
        return Storage::disk(self::DISCO)->exists($this->path);
    }

    public function getTamanoTextoAttribute(): string
    {
        return $this->tamano >= 1048576 ? round($this->tamano / 1048576, 1).' MB' : max(1, round($this->tamano / 1024)).' KB';
    }
}
