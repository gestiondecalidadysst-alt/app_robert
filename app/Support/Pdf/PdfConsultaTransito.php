<?php

namespace App\Support\Pdf;

use App\Models\Conductor;
use App\Models\ConsultaTransito;
use App\Models\Empresa;
use App\Models\Vehiculo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

/**
 * Constancia de una consulta de tránsito: lo que devolvió el SIMIT o el RUNT en la fecha y hora de la
 * consulta (comparendos con todo su detalle, o que no registra comparendos; licencia; RUNT). La expide
 * la empresa con el resultado guardado; no reemplaza el certificado oficial del SIMIT o el RUNT.
 */
final class PdfConsultaTransito
{
    public static function constancia(ConsultaTransito $consulta): string
    {
        $empresa = Empresa::query()->with('municipio')->findOrFail($consulta->empresa_id);
        $sujeto = $consulta->consultable;

        $html = view('pdf.constancia-transito', [
            'c' => $consulta,
            'd' => $consulta->detalle(),
            'empresa' => $empresa,
            'sujeto' => self::sujeto($sujeto, $consulta),
            'logo' => Imagenes::deDisco($empresa->logo_path, 'public'),
            'qr' => Imagenes::qr($consulta->urlVerificacion(), 3),
        ])->render();

        return Pdf::loadHTML($html)->setPaper('letter')
            ->setOption(['isFontSubsettingEnabled' => true, 'isRemoteEnabled' => false])->output();
    }

    /** @return list<array{0: string, 1: string}> filas etiqueta → valor del consultado */
    public static function sujeto(mixed $sujeto, ConsultaTransito $consulta): array
    {
        if ($sujeto instanceof Conductor) {
            $sujeto->loadMissing('tipoIdentificacion');

            return [
                ['Conductor', $sujeto->nombre_completo],
                ['Identificación', trim(($sujeto->tipoIdentificacion?->codigo ?? 'CC').' '.$sujeto->documento)],
            ];
        }
        if ($sujeto instanceof Vehiculo) {
            $sujeto->loadMissing(['marca', 'linea']);
            $propietario = $sujeto->propietario()->withoutGlobalScopes()->with('tipoIdentificacion')->first();

            return array_values(array_filter([
                ['Placa', Str::upper($sujeto->placa)],
                ['Vehículo', trim(collect([$sujeto->marca?->nombre, $sujeto->linea?->nombre, $sujeto->modelo])->filter()->implode(' ')) ?: '—'],
                $sujeto->numero_interno ? ['Número interno', (string) $sujeto->numero_interno] : null,
                $propietario ? ['Propietario', $propietario->nombre_completo.' · '.trim(($propietario->tipoIdentificacion?->codigo ?? 'CC').' '.$propietario->documento)] : null,
            ]));
        }

        return [['Consultado', $consulta->referencia]];
    }

    public static function nombre(ConsultaTransito $c): string
    {
        $d = $c->detalle();
        $prefijo = match ($d['clase']) {
            'comparendos' => $d['paz_y_salvo'] ? 'paz-y-salvo' : 'comparendos',
            'licencia' => 'licencia',
            default => 'runt',
        };

        return $prefijo.'-'.Str::slug($c->referencia).'-'.$c->created_at->format('Ymd').'.pdf';
    }
}
