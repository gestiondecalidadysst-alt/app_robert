<?php

namespace App\Support\Pdf;

use App\Models\Empresa;
use App\Services\Rndc\RegistrableRndc;
use App\Services\Rndc\Rndc;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Ficha para registrar terceros y vehículos en el RNDC: cada variable del diccionario con su valor,
 * lo que falta corregir y el estado del registro. Sirve para digitar en la web del RNDC mientras la
 * empresa no tenga conexión por web service, y como soporte del registro.
 */
final class PdfFichaRndc
{
    /** Descripción de cada variable (Manual RNDC Web-Service v2.3, diccionario de datos). */
    public const VARIABLES = [
        'NUMNITEMPRESATRANSPORTE' => 'NIT de la empresa de transporte',
        'CODTIPOIDTERCERO' => 'Tipo de identificación',
        'NUMIDTERCERO' => 'Número de identificación',
        'NOMIDTERCERO' => 'Nombre o razón social',
        'PRIMERAPELLIDOIDTERCERO' => 'Primer apellido',
        'SEGUNDOAPELLIDOIDTERCERO' => 'Segundo apellido',
        'CODSEDETERCERO' => 'Código de la sede',
        'NOMSEDETERCERO' => 'Nombre de la sede',
        'NUMTELEFONOCONTACTO' => 'Teléfono fijo',
        'NUMCELULARPERSONA' => 'Celular',
        'NOMENCLATURADIRECCION' => 'Dirección',
        'CODMUNICIPIORNDC' => 'Código del municipio (RNDC)',
        'CODCATEGORIALICENCIACONDUCCION' => 'Categoría de la licencia',
        'NUMLICENCIACONDUCCION' => 'Número de la licencia',
        'FECHAVENCIMIENTOLICENCIA' => 'Vencimiento de la licencia',
        'NUMPLACA' => 'Placa',
        'CODCONFIGURACIONUNIDADCARGA' => 'Configuración',
        'CODMARCAVEHICULOCARGA' => 'Código de la marca',
        'CODLINEAVEHICULOCARGA' => 'Código de la línea',
        'NUMEJES' => 'Número de ejes',
        'ANOFABRICACIONVEHICULOCARGA' => 'Año del modelo',
        'ANOREPOTENCIACION' => 'Año de repotenciación',
        'CODCOLORVEHICULOCARGA' => 'Código del color',
        'PESOVEHICULOVACIO' => 'Peso vacío (kg)',
        'CAPACIDADUNIDADCARGA' => 'Capacidad',
        'UNIDADMEDIDACAPACIDAD' => 'Unidad de la capacidad (1 kg, 2 galones)',
        'CODTIPOCARROCERIA' => 'Código del tipo de carrocería',
        'NUMCHASIS' => 'Número de chasis (serie)',
        'CODTIPOCOMBUSTIBLE' => 'Combustible (1 ACPM, 2 gasolina, 3 gas, 4 gas/gasolina)',
        'NUMSEGUROSOAT' => 'Póliza SOAT',
        'FECHAVENCIMIENTOSOAT' => 'Vencimiento del SOAT',
        'NUMNITASEGURADORASOAT' => 'NIT de la aseguradora del SOAT',
        'CODTIPOIDPROPIETARIO' => 'Tipo de identificación del propietario',
        'NUMIDPROPIETARIO' => 'Identificación del propietario',
        'CODTIPOIDTENEDOR' => 'Tipo de identificación del tenedor',
        'NUMIDTENEDOR' => 'Identificación del tenedor',
    ];

    /** @param  iterable<RegistrableRndc&Model>  $registros */
    public static function fichas(iterable $registros): string
    {
        $registros = Collection::make($registros)->values();
        $empresa = Empresa::query()->findOrFail($registros->first()->empresa_id);

        $html = view('pdf.ficha-rndc', [
            'empresa' => $empresa,
            'logo' => Imagenes::deDisco($empresa->logo_path, 'public'),
            'conectado' => Rndc::conectado($empresa),
            'fichas' => $registros->map(fn (Model $r) => [
                'titulo' => $r->etiquetaRndc(),
                'proceso' => $r->procesoRndc(),
                'variables' => $r->variablesRndc($empresa),
                'pendientes' => Rndc::pendientes($r),
                'estado' => $r->estadoRndc(),
                'ultimo' => $r->ultimoRegistroRndc()->first(),
            ]),
        ])->render();

        return Pdf::loadHTML($html)->setPaper('letter')->setOption(['isFontSubsettingEnabled' => true, 'isRemoteEnabled' => false])->output();
    }

    public static function nombre(Model $registro): string
    {
        return 'ficha-rndc-'.Str::slug($registro->placa ?? $registro->documento ?? $registro->getKey()).'.pdf';
    }
}
