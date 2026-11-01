<?php

namespace App\Models\Concerns;

use App\Models\Evidencia;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait TieneEvidencias
{
    public function evidencias(): MorphMany
    {
        return $this->morphMany(Evidencia::class, 'adjuntable')->orderBy('id');
    }
}
