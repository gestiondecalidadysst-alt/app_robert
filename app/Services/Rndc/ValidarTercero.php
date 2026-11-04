<?php

namespace App\Services\Rndc;

use App\Support\Carga\CatalogoRndc as C;

/** Validaciones del diccionario de datos de Terceros (proceso 11), comunes a todos los terceros. */
final class ValidarTercero
{
    /** @return list<string> */
    public static function errores(
        ?string $tipo, ?string $documento, ?string $nombre, ?string $primerApellido, ?string $telefono, ?string $celular,
        ?string $direccion, ?int $municipioId, ?string $juridicaConSede = null,
        ?string $categoriaLicencia = null, ?string $numeroLicencia = null, ?\DateTimeInterface $venceLicencia = null, bool $esConductor = false,
    ): array {
        $e = [];
        $documento = C::digitos($documento);
        if (! $tipo) {
            $e[] = 'El tipo de identificación no lo admite el RNDC (solo cédula, NIT, pasaporte, cédula de extranjería, tarjeta de identidad o NUIP).';
        }
        if (strlen($documento) < 5 || strlen($documento) > 15) {
            $e[] = 'El número de identificación debe tener entre 5 y 15 dígitos.';
        }
        if ($tipo === 'N' && strlen($documento) > 10) {
            $e[] = 'El NIT debe tener máximo 10 dígitos (con el dígito de verificación).';
        }
        if (blank($nombre)) {
            $e[] = 'Falta el nombre o la razón social.';
        }
        if ($tipo && $tipo !== 'N' && blank($primerApellido)) {
            $e[] = 'Falta el primer apellido (obligatorio para personas naturales).';
        }
        if ($tipo === 'N' && blank(C::digitos($telefono))) {
            $e[] = 'Las personas jurídicas deben tener teléfono fijo (7 dígitos).';
        }
        if ($tipo !== 'N' && blank(C::digitos($telefono)) && blank(C::digitos($celular))) {
            $e[] = 'Debe tener al menos un teléfono fijo o celular.';
        }
        if (filled($telefono) && strlen(C::digitos($telefono)) > 7) {
            $e[] = 'El teléfono fijo debe tener máximo 7 dígitos (sin indicativo).';
        }
        if (filled($celular) && strlen(C::digitos($celular)) !== 10) {
            $e[] = 'El celular debe tener 10 dígitos.';
        }
        if (blank($direccion)) {
            $e[] = 'Falta la dirección.';
        }
        if (! $municipioId) {
            $e[] = 'Falta el municipio.';
        }
        if ($juridicaConSede !== null && (mb_strlen($juridicaConSede) > 6 || $juridicaConSede === '')) {
            $e[] = 'El código de la sede debe tener hasta 6 caracteres.';
        }
        if ($esConductor) {
            if ($tipo && ! in_array($tipo, C::TIPOS_ID_CONDUCTOR, true)) {
                $e[] = 'Un conductor solo puede tener cédula de ciudadanía, cédula de extranjería o NUIP.';
            }
            if (! in_array(strtoupper((string) $categoriaLicencia), C::CATEGORIAS_LICENCIA, true)) {
                $e[] = 'La licencia de conducción debe ser categoría C1, C2 o C3 (o 4, 5, 6) y estar registrada en sus documentos.';
            }
            $n = C::digitos($numeroLicencia);
            if (strlen($n) < 6 || strlen($n) > 15) {
                $e[] = 'El número de la licencia debe tener entre 6 y 15 dígitos.';
            }
            if (! $venceLicencia || $venceLicencia < today()) {
                $e[] = 'La licencia de conducción está vencida o no tiene fecha de vencimiento.';
            }
        }

        return $e;
    }
}
