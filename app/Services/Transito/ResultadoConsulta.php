<?php

namespace App\Services\Transito;

/**
 * Resultado normalizado de una consulta al proveedor.
 * - ok: hubo datos. sinDatos: la persona o placa no tiene registros (no se cobra).
 * - cobrada: el proveedor la cobró (no se cobra si vino de su caché o sin datos).
 */
final class ResultadoConsulta
{
    public function __construct(
        public readonly string $estado, // ok | sin_datos | error
        public readonly array $datos = [],
        public readonly bool $cobrada = false,
        public readonly bool $desdeCache = false,
        public readonly ?string $error = null,
        public readonly ?int $duracionMs = null,
    ) {}

    public function ok(): bool
    {
        return $this->estado === 'ok';
    }
}
