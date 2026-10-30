<?php

namespace App\Models;

/**
 * Categoría de licencia de conducción.
 */
class CategoriaLicencia extends Catalogo
{
    protected $table = 'categorias_licencia';

    /** C1, C2 y C3 son de servicio público (exigidas en transporte especial); A y B, particular. */
    public function esServicioPublico(): bool
    {
        return str_starts_with(strtoupper((string) $this->codigo), 'C');
    }

    public function getNombreCompletoAttribute(): string
    {
        return "{$this->codigo} · {$this->descripcion}";
    }
}
