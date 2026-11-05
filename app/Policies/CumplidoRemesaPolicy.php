<?php
namespace App\Policies;
use App\Policies\Concerns\AutorizaPorPermiso;
class CumplidoRemesaPolicy
{
    use AutorizaPorPermiso;
    public const RECURSO = 'cumplidos_carga';
}
