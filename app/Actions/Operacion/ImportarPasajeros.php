<?php

namespace App\Actions\Operacion;

use App\Models\Fuec;
use App\Models\PasajeroFuec;
use Illuminate\Support\Str;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Importa pasajeros a un listado de FUEC desde un archivo Excel (.xlsx) o CSV.
 *
 * Columnas esperadas (con encabezado, en cualquier orden; o sin encabezado en este orden):
 * nombre, tipo_documento, documento, telefono, es_menor, acudiente_nombre, acudiente_documento.
 *
 * Respeta el cupo del vehículo, evita duplicados por documento y exige acudiente a los menores.
 *
 * @return array{importados:int, omitidos:int, errores:list<string>}
 */
class ImportarPasajeros
{
    /** Orden por defecto de las columnas cuando el archivo no trae encabezado reconocible. */
    private const ORDEN = ['nombre', 'tipo_documento', 'documento', 'telefono', 'es_menor', 'acudiente_nombre', 'acudiente_documento'];

    public function __invoke(Fuec $fuec, string $ruta): array
    {
        $importados = 0;
        $omitidos = 0;
        $errores = [];

        $limite = $fuec->limitePasajeros();
        $existentes = $fuec->pasajeros()->pluck('documento')
            ->map(fn ($d) => $this->normalizar($d))->filter()->flip();
        $orden = (int) $fuec->pasajeros()->max('orden');
        $total = $fuec->pasajeros()->count();

        $reader = $this->lector($ruta);
        $reader->open($ruta);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $mapa = null;

                foreach ($sheet->getRowIterator() as $numero => $fila) {
                    $celdas = array_map(fn ($c) => trim((string) $c), $fila->toArray());

                    // Primera fila: ¿es encabezado? Si reconoce columnas, arma el mapa; si no, orden fijo.
                    if ($mapa === null) {
                        $mapa = $this->mapaColumnas($celdas);
                        if ($mapa !== null) {
                            continue; // era encabezado, no es un pasajero
                        }
                        $mapa = array_flip(self::ORDEN);
                    }

                    $datos = $this->extraer($celdas, $mapa);

                    if ($datos['nombre'] === '' && $datos['documento'] === '') {
                        continue; // fila vacía
                    }

                    if ($total >= $limite) {
                        $errores[] = "Fila {$numero}: se alcanzó el cupo ({$limite}). No se importaron más.";
                        $omitidos++;

                        break 2;
                    }

                    $error = $this->validar($datos);
                    if ($error !== null) {
                        $errores[] = "Fila {$numero}: {$error}";
                        $omitidos++;

                        continue;
                    }

                    $doc = $this->normalizar($datos['documento']);
                    if ($existentes->has($doc)) {
                        $omitidos++;

                        continue; // duplicado dentro del archivo o ya registrado
                    }

                    PasajeroFuec::create([
                        'empresa_id' => $fuec->empresa_id,
                        'fuec_id' => $fuec->id,
                        'orden' => ++$orden,
                        'nombre' => $datos['nombre'],
                        'tipo_documento' => $datos['tipo_documento'],
                        'documento' => $datos['documento'],
                        'telefono' => $datos['telefono'] ?: null,
                        'es_menor' => $datos['es_menor'],
                        'acudiente_nombre' => $datos['es_menor'] ? $datos['acudiente_nombre'] : null,
                        'acudiente_documento' => $datos['es_menor'] ? ($datos['acudiente_documento'] ?: null) : null,
                        'registrado_at' => now(),
                    ]);

                    $existentes->put($doc, true);
                    $importados++;
                    $total++;
                }

                break; // solo la primera hoja
            }
        } finally {
            $reader->close();
        }

        return ['importados' => $importados, 'omitidos' => $omitidos, 'errores' => $errores];
    }

    private function lector(string $ruta): ReaderInterface
    {
        return strtolower(pathinfo($ruta, PATHINFO_EXTENSION)) === 'csv'
            ? new CsvReader
            : new XlsxReader;
    }

    /** @return array<string,int>|null mapa columna→índice si la fila es un encabezado reconocible */
    private function mapaColumnas(array $celdas): ?array
    {
        $alias = [
            'nombre' => 'nombre', 'nombres' => 'nombre', 'pasajero' => 'nombre', 'nombre completo' => 'nombre',
            'tipo_documento' => 'tipo_documento', 'tipo documento' => 'tipo_documento', 'tipo' => 'tipo_documento',
            'documento' => 'documento', 'identificacion' => 'documento', 'cedula' => 'documento', 'numero documento' => 'documento',
            'telefono' => 'telefono', 'celular' => 'telefono', 'tel' => 'telefono',
            'es_menor' => 'es_menor', 'menor' => 'es_menor', 'es menor' => 'es_menor',
            'acudiente_nombre' => 'acudiente_nombre', 'acudiente' => 'acudiente_nombre', 'nombre acudiente' => 'acudiente_nombre',
            'acudiente_documento' => 'acudiente_documento', 'documento acudiente' => 'acudiente_documento',
        ];

        $mapa = [];
        foreach ($celdas as $i => $valor) {
            $clave = Str::ascii(Str::lower($valor));
            if (isset($alias[$clave])) {
                $mapa[$alias[$clave]] = $i;
            }
        }

        // Es encabezado solo si reconoció al menos nombre y documento.
        return isset($mapa['nombre'], $mapa['documento']) ? $mapa : null;
    }

    /** @return array{nombre:string, tipo_documento:string, documento:string, telefono:string, es_menor:bool, acudiente_nombre:string, acudiente_documento:string} */
    private function extraer(array $celdas, array $mapa): array
    {
        $en = fn (string $col) => isset($mapa[$col]) ? (string) ($celdas[$mapa[$col]] ?? '') : '';

        $tipo = Str::upper($en('tipo_documento')) ?: 'CC';

        return [
            'nombre' => $en('nombre'),
            'tipo_documento' => array_key_exists($tipo, PasajeroFuec::TIPOS_DOCUMENTO) ? $tipo : 'CC',
            'documento' => preg_replace('/[^0-9A-Za-z]/', '', $en('documento')) ?? '',
            'telefono' => $en('telefono'),
            'es_menor' => $this->esSi($en('es_menor')),
            'acudiente_nombre' => $en('acudiente_nombre'),
            'acudiente_documento' => preg_replace('/[^0-9A-Za-z]/', '', $en('acudiente_documento')) ?? '',
        ];
    }

    private function validar(array $datos): ?string
    {
        if ($datos['nombre'] === '') {
            return 'falta el nombre.';
        }
        if ($datos['documento'] === '') {
            return 'falta el documento.';
        }
        if ($datos['es_menor'] && $datos['acudiente_nombre'] === '') {
            return 'es menor y falta el nombre del acudiente.';
        }

        return null;
    }

    private function esSi(string $valor): bool
    {
        return in_array(Str::ascii(Str::lower(trim($valor))), ['si', 'sí', 's', '1', 'x', 'true', 'menor', 'verdadero'], true);
    }

    private function normalizar(?string $documento): string
    {
        return Str::lower(preg_replace('/[^0-9A-Za-z]/', '', (string) $documento) ?? '');
    }
}
