<?php

namespace App\Services\Rndc;

/** Respuesta del RNDC: número de radicado (ingresoid) o la lista de errores. */
final class RespuestaRndc
{
    /** @param list<array{codigo: ?string, mensaje: string}> $errores */
    public function __construct(
        public readonly ?string $ingresoid,
        public readonly array $errores,
        public readonly string $crudo,
        public readonly int $duracionMs = 0,
    ) {}

    public function ok(): bool
    {
        return $this->ingresoid !== null && $this->errores === [];
    }

    /** Interpreta el XML de respuesta: <root><ingresoid>12</ingresoid></root> o el texto del error. */
    public static function desdeXml(string $xml, int $duracionMs = 0): self
    {
        $texto = trim($xml);
        if (preg_match('/<ingresoid>\s*([^<\s]+)\s*<\/ingresoid>/i', $texto, $m)) {
            return new self($m[1], [], $texto, $duracionMs);
        }

        $mensajes = [];
        if (preg_match_all('/<(ErrorMSG|error|errores|mensaje)>(.*?)<\/\1>/is', $texto, $mm)) {
            $mensajes = $mm[2];
        } else {
            $mensajes = [strip_tags($texto) ?: 'El RNDC no devolvió un número de radicado.'];
        }

        $errores = [];
        foreach ($mensajes as $linea) {
            foreach (preg_split('/\r\n|\n|\|/', html_entity_decode(strip_tags($linea))) as $parte) {
                $parte = trim($parte);
                if ($parte === '') {
                    continue;
                }
                $codigo = preg_match('/\b([A-Z]{3}\d{3})\b/', $parte, $c) ? $c[1] : null;
                $errores[] = ['codigo' => $codigo, 'mensaje' => $parte];
            }
        }

        return new self(null, $errores ?: [['codigo' => null, 'mensaje' => 'Respuesta sin radicado del RNDC.']], $texto, $duracionMs);
    }
}
