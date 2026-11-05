<?php

namespace App\Support\Carga;

use App\Models\ManifiestoCarga;
use App\Models\Municipio;
use App\Models\TarifaSicetac;
use Illuminate\Support\Carbon;

/**
 * Costo mínimo del SICE-TAC para un viaje.
 *
 * El Ministerio no publica un servicio de consulta, así que el valor sale de las
 * tablas que la empresa carga en Carga → SICE-TAC. Si no hay tarifa para la ruta,
 * el sistema no inventa un mínimo: lo dice y deja que el RNDC valide al radicar.
 */
final class Sicetac
{
    /** Costo mínimo para esa ruta y configuración, o null si no hay tarifa cargada. */
    public static function minimo(
        int $empresaId,
        ?int $origenId,
        ?int $destinoId,
        ?int $configuracion = null,
        ?Carbon $fecha = null,
    ): ?int {
        if (! $origenId || ! $destinoId) {
            return null;
        }

        $fecha = $fecha ?? now();

        $tarifa = TarifaSicetac::query()->deEmpresa($empresaId)
            ->where('origen_id', $origenId)
            ->where('destino_id', $destinoId)
            ->where('vigente_desde', '<=', $fecha)
            ->where(fn ($query) => $query->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', $fecha))
            // La tarifa de la configuración exacta manda sobre la genérica.
            ->where(fn ($query) => $query->where('configuracion', $configuracion)->orWhereNull('configuracion'))
            ->orderByRaw('configuracion is null')
            ->orderByDesc('vigente_desde')
            ->first();

        return $tarifa?->valor_minimo;
    }

    /** El mínimo que corresponde a un manifiesto, por su ruta y el vehículo que lleva. */
    public static function paraManifiesto(ManifiestoCarga $manifiesto): ?int
    {
        return self::minimo(
            (int) $manifiesto->empresa_id,
            $manifiesto->origen_id,
            $manifiesto->destino_id,
            $manifiesto->vehiculo?->rndc_configuracion,
            $manifiesto->fecha_expedicion ? Carbon::parse($manifiesto->fecha_expedicion) : null,
        );
    }

    /**
     * Carga tarifas desde un CSV: origen;destino;configuracion;valor;desde;hasta
     * con los municipios por código DANE. Devuelve [cargadas, errores].
     *
     * @return array{0: int, 1: list<string>}
     */
    public static function importar(int $empresaId, string $csv): array
    {
        $municipios = Municipio::query()->pluck('id', 'codigo_dane');
        $cargadas = 0;
        $errores = [];

        foreach (preg_split('/\r\n|\r|\n/', trim($csv)) as $n => $linea) {
            $linea = trim($linea);

            if ($linea === '' || str_starts_with(mb_strtolower($linea), 'origen')) {
                continue; // vacía o cabecera
            }

            $campos = array_map('trim', str_getcsv($linea, ';'));
            $fila = $n + 1;

            if (count($campos) < 4) {
                $errores[] = "Línea {$fila}: se esperan al menos origen;destino;configuracion;valor.";

                continue;
            }

            [$origen, $destino, $configuracion, $valor] = $campos;
            $desde = $campos[4] ?? null;
            $hasta = $campos[5] ?? null;

            if (! isset($municipios[$origen]) || ! isset($municipios[$destino])) {
                $errores[] = "Línea {$fila}: código DANE desconocido ({$origen} o {$destino}).";

                continue;
            }

            if (! is_numeric($valor) || (int) $valor <= 0) {
                $errores[] = "Línea {$fila}: el valor debe ser un número mayor que cero.";

                continue;
            }

            // La fecha se normaliza antes de buscar: si se compara la cadena cruda contra
            // la columna de fecha no encuentra la fila y se intenta insertar una duplicada.
            try {
                $vigenteDesde = $desde ? Carbon::parse($desde)->startOfDay() : today();
            } catch (\Throwable) {
                $errores[] = "Línea {$fila}: la fecha «{$desde}» no se entiende (use AAAA-MM-DD).";

                continue;
            }

            TarifaSicetac::updateOrCreate(
                [
                    'empresa_id' => $empresaId,
                    'origen_id' => $municipios[$origen],
                    'destino_id' => $municipios[$destino],
                    'configuracion' => $configuracion === '' ? null : (int) $configuracion,
                    'vigente_desde' => $vigenteDesde,
                ],
                ['valor_minimo' => (int) $valor, 'vigente_hasta' => $hasta ? Carbon::parse($hasta)->startOfDay() : null],
            );

            $cargadas++;
        }

        return [$cargadas, $errores];
    }
}
