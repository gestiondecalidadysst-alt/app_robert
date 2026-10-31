<?php

namespace App\Support;

/**
 * Normaliza y enmascara celulares colombianos y correos.
 */
final class Contacto
{
    /** "+57 300 123 4567" → "3001234567". Null si no es un celular colombiano válido. */
    public static function celular(?string $numero): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $numero);

        if (strlen($digitos) === 12 && str_starts_with($digitos, '57')) {
            $digitos = substr($digitos, 2);
        }

        return preg_match('/^3\d{9}$/', $digitos) ? $digitos : null;
    }

    /** "3001234567" → "••• ••• 4567". */
    public static function ocultarCelular(?string $numero): string
    {
        $digitos = preg_replace('/\D/', '', (string) $numero);

        return $digitos === '' ? '—' : '••• ••• ' . substr($digitos, -4);
    }

    /** "rectoria@colegio.edu.co" → "re•••@colegio.edu.co". */
    public static function ocultarCorreo(?string $correo): string
    {
        if (! $correo || ! str_contains($correo, '@')) {
            return '—';
        }

        [$usuario, $dominio] = explode('@', $correo, 2);

        return mb_substr($usuario, 0, 2) . '•••@' . $dominio;
    }

    public static function correoValido(?string $correo): bool
    {
        return filled($correo) && filter_var($correo, FILTER_VALIDATE_EMAIL) !== false;
    }

    /** Texto apto para un SMS de 160 caracteres: sin tildes, ñ ni caracteres especiales. */
    public static function textoSms(string $texto): string
    {
        $texto = strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
            '¿' => '', '¡' => '', '°' => 'o', '“' => '"', '”' => '"', '‘' => "'", '’' => "'", '–' => '-', '—' => '-',
        ]);

        return trim(preg_replace('/[^\x20-\x7E]/', '', $texto));
    }
}
