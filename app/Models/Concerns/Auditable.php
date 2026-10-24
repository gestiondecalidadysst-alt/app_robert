<?php

namespace App\Models\Concerns;

use App\Models\Empresa;
use Filament\Facades\Filament;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Registra en auditoría la creación, edición y borrado del modelo,
 * guardando la empresa en la que ocurrió el cambio.
 */
trait Auditable
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(['password', 'remember_token', 'updated_at', 'created_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName(class_basename(static::class));
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        $tenant = Filament::getTenant();

        $activity->empresa_id = match (true) {
            $tenant instanceof Empresa => $tenant->getKey(),
            $this instanceof Empresa => $this->getKey(),
            default => $this->getAttribute('empresa_id'),
        };
    }
}
