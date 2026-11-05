<?php

namespace App\Actions\Contratos;

use App\Models\Empresa;
use App\Models\PlantillaContrato;
use App\Support\Contratos\PlantillaBase;
use App\Support\Contratos\VariablesContrato;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Guarda una nueva versión de la plantilla del contrato. Las versiones anteriores no cambian:
 * los contratos ya emitidos conservan el texto con el que se emitieron.
 */
class GuardarPlantillaContrato
{
    public function __invoke(Empresa $empresa, array $datos): PlantillaContrato
    {
        $contenido = trim((string) ($datos['contenido'] ?? ''));

        if (blank(strip_tags($contenido))) {
            throw ValidationException::withMessages(['contenido' => 'La plantilla no puede quedar vacía.']);
        }

        $desconocidas = array_diff(VariablesContrato::usadas($contenido), array_keys(VariablesContrato::definiciones()));
        if ($desconocidas) {
            throw ValidationException::withMessages([
                'contenido' => 'Variables desconocidas: '.implode(', ', $desconocidas).'. Vuelva a insertarlas desde la lista.',
            ]);
        }

        return DB::transaction(function () use ($empresa, $datos, $contenido): PlantillaContrato {
            Empresa::query()->whereKey($empresa->getKey())->lockForUpdate()->first();

            $ultima = (int) PlantillaContrato::query()->deEmpresa($empresa)->max('version');
            $version = (int) ($datos['version'] ?? 0) ?: $ultima + 1;

            if ($version <= $ultima) {
                throw ValidationException::withMessages([
                    'version' => 'La versión debe ser mayor que la vigente ('.str_pad((string) $ultima, 2, '0', STR_PAD_LEFT).').',
                ]);
            }

            $plantilla = new PlantillaContrato([
                'version' => $version,
                'codigo_formato' => $datos['codigo_formato'] ?? null,
                'titulo' => filled($datos['titulo'] ?? null) ? $datos['titulo'] : PlantillaBase::TITULO,
                'contenido' => $contenido,
                'notas_cambio' => $datos['notas_cambio'] ?? null,
                'user_id' => auth()->id(),
            ]);
            $plantilla->empresa_id = $empresa->id;
            $plantilla->save();

            return $plantilla;
        });
    }
}
