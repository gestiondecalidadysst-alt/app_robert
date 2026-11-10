<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Una sección del manual de la aplicación (contenido global, editable desde el panel).
 */
class SeccionManual extends Model
{
    protected $table = 'secciones_manual';

    protected $fillable = ['grupo', 'titulo', 'slug', 'orden', 'icono', 'contenido'];

    protected static function booted(): void
    {
        static::saving(function (SeccionManual $seccion): void {
            if (blank($seccion->slug)) {
                $seccion->slug = static::slugUnico($seccion->titulo);
            }
        });
    }

    public function scopeOrdenado(Builder $query): Builder
    {
        return $query->orderBy('grupo')->orderBy('orden')->orderBy('titulo');
    }

    public static function slugUnico(string $titulo): string
    {
        $base = Str::slug($titulo) ?: 'seccion';
        $slug = $base;
        $i = 2;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
