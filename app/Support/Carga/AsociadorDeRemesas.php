<?php

namespace App\Support\Carga;

use App\Models\ManifiestoCarga;
use App\Models\RemesaCarga;

/**
 * Engancha remesas a un manifiesto.
 *
 * Filtra siempre por la empresa del manifiesto con `deEmpresa()`, que descarta los
 * global scopes (el propio y el de Filament): un `update()` que dependa del tenant
 * activo puede no tocar ninguna fila según el contexto, y lo haría en silencio.
 */
final class AsociadorDeRemesas
{
    /**
     * @param  array<int>  $ids
     * @return int Remesas efectivamente asociadas al manifiesto.
     */
    public static function aplicar(ManifiestoCarga $manifiesto, array $ids): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));

        // Suelta las que se quitaron (si no queda ninguna, suelta todas las del manifiesto).
        $sueltas = RemesaCarga::query()->deEmpresa($manifiesto->empresa_id)->where('manifiesto_id', $manifiesto->id);

        if ($ids !== []) {
            $sueltas->whereNotIn('id', $ids);
        }

        $sueltas->update(['manifiesto_id' => null]);

        if ($ids !== []) {
            RemesaCarga::query()->deEmpresa($manifiesto->empresa_id)
                ->whereIn('id', $ids)->whereNull('anulado_at')
                ->update(['manifiesto_id' => $manifiesto->id]);
        }

        $manifiesto->unsetRelation('remesas');

        return RemesaCarga::query()->deEmpresa($manifiesto->empresa_id)
            ->where('manifiesto_id', $manifiesto->id)->count();
    }
}
