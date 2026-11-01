<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AutorizaPorPermiso;

class ItemChequeoPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'items_chequeo';

    public function reorder(User $user): bool
    {
        return $this->puede($user, 'editar');
    }
}
