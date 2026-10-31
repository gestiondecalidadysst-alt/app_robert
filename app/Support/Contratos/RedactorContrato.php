<?php

namespace App\Support\Contratos;

use App\Models\Contrato;
use App\Models\Empresa;
use App\Models\PlantillaContrato;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Support\Carbon;

/**
 * Diligencia la plantilla del contrato con los datos de un contrato.
 * Lo usan la vista previa y la emisión (que exige que no falte ningún dato).
 */
final class RedactorContrato
{
    /**
     * @return array{html: string, faltantes: list<string>, valores: array<string, string>}
     */
    public static function componer(Contrato $contrato, PlantillaContrato $plantilla, Empresa $empresa, ?Carbon $fecha = null): array
    {
        $usadas = VariablesContrato::usadas($plantilla->contenido);
        $resultado = VariablesContrato::resolver(VariablesContrato::valores($contrato, $empresa, $fecha), $usadas);

        $html = RichContentRenderer::make($plantilla->contenido)
            ->mergeTags($resultado['valores'])
            ->toHtml();

        // El valor queda como texto normal (sin <span>): dompdf pierde espacios junto a elementos en línea.
        $html = preg_replace('/<span\b[^>]*data-type="mergeTag"[^>]*>(.*?)<\/span>/su', '$1', $html);

        // Texto con llaves escritas a mano (no insertadas desde la lista): no se reemplaza.
        if (preg_match('/\{\{\s*[\w.]+\s*\}\}/u', strip_tags($html))) {
            $resultado['faltantes'][] = 'Hay variables escritas a mano entre {{ }}; insértelas desde el botón de variables del editor';
        }

        return ['html' => $html, 'faltantes' => $resultado['faltantes'], 'valores' => $resultado['valores']];
    }

    /** Datos del encabezado, el pie y el bloque de firmas, copiados al emitir. */
    public static function datosEmpresa(Empresa $empresa, PlantillaContrato $plantilla): array
    {
        $valores = VariablesContrato::valores(new Contrato, $empresa);

        return [
            'razon_social' => $empresa->razon_social,
            'nombre_comercial' => $empresa->nombre_comercial,
            'nit' => $valores['empresa.nit'],
            'direccion' => $empresa->direccion,
            'ciudad' => $valores['empresa.ciudad'],
            'telefono' => $empresa->telefono,
            'email' => $empresa->email,
            'representante' => $valores['empresa.representante'],
            'representante_documento' => $valores['empresa.representante_documento'],
            'representante_expedicion' => $empresa->representante_expedicion,
            'titulo_formato' => $plantilla->titulo,
            'codigo_formato' => $plantilla->codigo_formato,
            'version_formato' => $plantilla->version_formateada,
        ];
    }
}
