<?php

namespace App\Support\Carga;

/**
 * Códigos del RNDC (Manual RNDC Web-Service v2.3 y tablas del Anexo a los Manuales RNDC).
 * Las configuraciones se tomaron del manual; las marcas, líneas, colores, carrocerías y
 * aseguradoras tienen tablas propias del anexo y se digitan con su código.
 */
final class CatalogoRndc
{
    /** Tipo de identificación del sistema (tipos_identificacion.codigo) => código del RNDC. */
    public const TIPOS_ID = ['CC' => 'C', 'NIT' => 'N', 'PA' => 'P', 'CE' => 'E', 'TI' => 'T', 'NUIP' => 'U'];

    /** Los conductores solo pueden ser C, E o U. */
    public const TIPOS_ID_CONDUCTOR = ['C', 'E', 'U'];

    public const CATEGORIAS_LICENCIA = ['4', '5', '6', 'C1', 'C2', 'C3'];

    /** Configuraciones de vehículos motorizados (camión rígido y tractocamión). */
    public const CONFIGURACIONES_VEHICULO = [
        50 => 'Camión rígido de 2 ejes',
        51 => 'Camión rígido de 3 ejes',
        52 => 'Camión rígido de 4 ejes',
        53 => 'Tractocamión de 2 ejes',
        54 => 'Tractocamión de 3 ejes',
        55 => 'Tractocamión de más de 3 ejes',
        56 => 'Camión rígido de más de 4 ejes',
    ];

    /** Configuraciones de unidades de arrastre (placa R). */
    public const CONFIGURACIONES_REMOLQUE = [
        61 => 'Semirremolque de 1 eje',
        62 => 'Semirremolque de 2 ejes',
        63 => 'Semirremolque de 3 ejes',
        64 => 'Semirremolque de más de 3 ejes',
        71 => 'Remolque de 1 eje',
        72 => 'Remolque de 2 ejes',
        73 => 'Remolque de 3 ejes',
        74 => 'Remolque de más de 4 ejes',
        81 => 'Remolque balanceado de 1 eje',
        82 => 'Remolque balanceado de 2 ejes',
        83 => 'Remolque balanceado de 3 ejes',
        84 => 'Remolque balanceado de 4 ejes',
        85 => 'Remolque balanceado de más de 4 ejes',
    ];

    /** Configuraciones que exigen número de ejes y capacidad. */
    public const CON_EJES = [55, 56, 64, 74, 85];

    public const CON_CAPACIDAD = [50, 55, 56, 64, 74, 85];

    /** Tractocamiones: carrocería S.R.S. (código 0). */
    public const TRACTOCAMIONES = [53, 54, 55];

    public const COMBUSTIBLES = [1 => 'Diésel (ACPM)', 2 => 'Gasolina', 3 => 'Gas', 4 => 'Gas y gasolina'];

    public const UNIDADES_CAPACIDAD = [1 => 'Kilogramos', 2 => 'Galones'];

    public const ROLES_TERCERO = [
        'remitente' => 'Remitente',
        'destinatario' => 'Destinatario',
        'titular' => 'Titular de manifiesto',
        'generador' => 'Generador de carga',
    ];

    /** Procesos del web service. */
    public const PROCESOS = [
        11 => 'Tercero',
        12 => 'Vehículo',
    ];

    /** Código de municipio del RNDC: código DANE de 5 dígitos + 000. */
    public static function municipio(?string $dane): ?string
    {
        return $dane ? str_pad($dane, 5, '0', STR_PAD_LEFT) . '000' : null;
    }

    public static function tipoId(?string $codigo): ?string
    {
        return self::TIPOS_ID[strtoupper((string) $codigo)] ?? null;
    }

    /** Solo dígitos (teléfonos, documentos). */
    public static function digitos(?string $valor): string
    {
        return preg_replace('/\D/', '', (string) $valor);
    }

    /** Texto en mayúsculas sin caracteres que el RNDC rechaza. */
    public static function texto(?string $valor, int $max): string
    {
        $t = mb_strtoupper(trim(preg_replace('/\s+/', ' ', (string) $valor)));

        return mb_substr($t, 0, $max);
    }
}
