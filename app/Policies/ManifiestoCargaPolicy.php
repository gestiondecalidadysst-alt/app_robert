<?php
namespace App\Policies;
use App\Policies\Concerns\AutorizaPorPermiso;
class ManifiestoCargaPolicy
{
    use AutorizaPorPermiso;
    public const RECURSO = 'manifiestos_carga';
}
