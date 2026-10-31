<?php

namespace App\Services\Mensajeria;

final class ResultadoSms
{
    public function __construct(
        public readonly bool $exito,
        public readonly ?string $referencia = null,
        public readonly ?string $error = null,
    ) {}
}
