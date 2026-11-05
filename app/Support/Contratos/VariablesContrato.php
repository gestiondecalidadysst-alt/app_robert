<?php

namespace App\Support\Contratos;

use App\Enums\FormaPago;
use App\Models\Contrato;
use App\Models\Empresa;
use App\Support\Nit;
use App\Support\NumeroEnLetras;
use Illuminate\Support\Carbon;

/**
 * Variables que se pueden insertar en la plantilla del contrato (merge tags del editor).
 *
 * Cada variable tiene una etiqueta, si es obligatoria y cómo se calcula. Al emitir, una variable
 * obligatoria sin valor bloquea la emisión con su etiqueta ("Contratante · Teléfono"). Las
 * opcionales sin valor se reemplazan por su texto por defecto.
 */
final class VariablesContrato
{
    /** @return array<string, array{0: string, 1: bool, 2: string}> id => [etiqueta, obligatoria, por defecto] */
    public static function definiciones(): array
    {
        return [
            // Contrato
            'contrato.numero' => ['Contrato · Número', true, ''],
            'contrato.objeto' => ['Contrato · Objeto', true, ''],
            'contrato.descripcion_objeto' => ['Contrato · Descripción del objeto', false, ''],
            'contrato.pasajeros' => ['Contrato · Cantidad de pasajeros', true, ''],
            'contrato.vehiculos' => ['Contrato · Cantidad de vehículos', false, '1'],
            'contrato.fecha_inicio' => ['Contrato · Fecha de inicio', true, ''],
            'contrato.fecha_fin' => ['Contrato · Fecha de terminación', true, ''],
            'contrato.hora_inicio' => ['Contrato · Hora de inicio', true, ''],
            'contrato.hora_fin' => ['Contrato · Hora de terminación', true, ''],
            'contrato.horas_disponibilidad' => ['Contrato · Horas de disponibilidad', true, ''],
            'contrato.origen' => ['Contrato · Origen', true, ''],
            'contrato.destino' => ['Contrato · Destino', true, ''],
            'contrato.recorrido' => ['Contrato · Recorrido', false, 'el acordado entre las partes'],
            'contrato.zonas_disposicion' => ['Contrato · Zonas de disposición total', false, 'No aplica'],
            'contrato.valor_letras' => ['Contrato · Valor en letras', true, ''],
            'contrato.valor_numeros' => ['Contrato · Valor en números', true, ''],
            'contrato.forma_pago' => ['Contrato · Forma de pago', true, ''],
            'contrato.responsable_nombre' => ['Contrato · Responsable (nombre)', false, 'No registrado'],
            'contrato.responsable_documento' => ['Contrato · Responsable (documento)', false, 'No registrado'],
            'contrato.responsable_telefono' => ['Contrato · Responsable (teléfono)', false, 'No registrado'],

            // Contratante (cliente)
            'contratante.nombre' => ['Contratante · Nombre o razón social', true, ''],
            'contratante.identificacion' => ['Contratante · Identificación (C.C./NIT)', true, ''],
            'contratante.ciudad' => ['Contratante · Ciudad', true, ''],
            'contratante.direccion' => ['Contratante · Dirección', true, ''],
            'contratante.telefono' => ['Contratante · Teléfono', true, ''],
            'contratante.email' => ['Contratante · Correo', false, 'No registrado'],

            // Empresa (contratista)
            'empresa.razon_social' => ['Empresa · Razón social', true, ''],
            'empresa.nombre_comercial' => ['Empresa · Nombre comercial', false, ''],
            'empresa.nit' => ['Empresa · NIT', true, ''],
            'empresa.ciudad' => ['Empresa · Ciudad', true, ''],
            'empresa.direccion' => ['Empresa · Dirección', true, ''],
            'empresa.telefono' => ['Empresa · Teléfono', true, ''],
            'empresa.email' => ['Empresa · Correo', true, ''],
            'empresa.sitio_web' => ['Empresa · Sitio web', true, ''],
            'empresa.representante' => ['Empresa · Representante legal', true, ''],
            'empresa.representante_documento' => ['Empresa · Cédula del representante', true, ''],
            'empresa.representante_expedicion' => ['Empresa · Cédula del representante expedida en', true, ''],

            // Documento
            'documento.fecha_emision' => ['Documento · Fecha de emisión', true, ''],
            'documento.fecha_emision_larga' => ['Documento · Fecha de emisión ("31 días del mes de…")', true, ''],
        ];
    }

    /** Opciones para el editor: id => etiqueta. */
    public static function etiquetas(): array
    {
        return array_map(fn (array $d) => $d[0], self::definiciones());
    }

    public static function etiqueta(string $id): string
    {
        return self::definiciones()[$id][0] ?? $id;
    }

    /**
     * Valores calculados (null = sin dato) para un contrato.
     *
     * @return array<string, ?string>
     */
    public static function valores(Contrato $contrato, Empresa $empresa, ?Carbon $fechaEmision = null): array
    {
        $contrato->loadMissing(['cliente.tipoIdentificacion', 'cliente.municipio', 'cliente.expedicionMunicipio', 'origen', 'destino']);
        $empresa->loadMissing('municipio');
        $cliente = $contrato->cliente;
        $fecha = $fechaEmision ?? now();
        $valor = (float) $contrato->valor;

        return [
            'contrato.numero' => $contrato->numero_formateado,
            'contrato.objeto' => $contrato->objeto?->getLabel(),
            'contrato.descripcion_objeto' => $contrato->descripcion_objeto,
            'contrato.pasajeros' => $contrato->cantidad_pasajeros ? (string) $contrato->cantidad_pasajeros : null,
            'contrato.vehiculos' => $contrato->cantidad_vehiculos ? (string) $contrato->cantidad_vehiculos : null,
            'contrato.fecha_inicio' => self::fecha($contrato->fecha_inicio),
            'contrato.fecha_fin' => self::fecha($contrato->fecha_fin),
            'contrato.hora_inicio' => self::hora($contrato->hora_inicio),
            'contrato.hora_fin' => self::hora($contrato->hora_fin),
            'contrato.horas_disponibilidad' => self::horasEntre($contrato->hora_inicio, $contrato->hora_fin),
            'contrato.origen' => self::municipio($contrato->origen?->nombre),
            'contrato.destino' => self::municipio($contrato->destino?->nombre),
            'contrato.recorrido' => $contrato->recorrido,
            'contrato.zonas_disposicion' => $contrato->disposicion_total ? $contrato->zonas_disposicion : null,
            'contrato.valor_letras' => $valor > 0 ? NumeroEnLetras::pesos($valor) : null,
            'contrato.valor_numeros' => $valor > 0 ? '$ '.number_format($valor, 0, ',', '.') : null,
            'contrato.forma_pago' => self::formaPago($contrato),
            'contrato.responsable_nombre' => $contrato->responsable_nombre,
            'contrato.responsable_documento' => $contrato->responsable_documento,
            'contrato.responsable_telefono' => $contrato->responsable_telefono,

            'contratante.nombre' => $cliente?->nombre,
            'contratante.identificacion' => self::identificacionCliente($contrato),
            'contratante.ciudad' => self::municipio($cliente?->municipio?->nombre),
            'contratante.direccion' => $cliente?->direccion,
            'contratante.telefono' => $cliente?->telefono,
            'contratante.email' => $cliente?->email,

            'empresa.razon_social' => $empresa->razon_social,
            'empresa.nombre_comercial' => $empresa->nombre_comercial ?: $empresa->razon_social,
            'empresa.nit' => $empresa->nit ? Nit::formatear($empresa->nit, $empresa->dv) : null,
            'empresa.ciudad' => self::municipio($empresa->municipio?->nombre),
            'empresa.direccion' => $empresa->direccion,
            'empresa.telefono' => $empresa->telefono,
            'empresa.email' => $empresa->email,
            'empresa.sitio_web' => $empresa->sitio_web,
            'empresa.representante' => $empresa->representante_legal ? mb_strtoupper($empresa->representante_legal) : null,
            'empresa.representante_documento' => self::numeroDocumento($empresa->representante_documento),
            'empresa.representante_expedicion' => $empresa->representante_expedicion,

            'documento.fecha_emision' => self::fecha($fecha),
            'documento.fecha_emision_larga' => $fecha->day.' días del mes de '.$fecha->locale('es')->translatedFormat('F').' de '.$fecha->year,
        ];
    }

    /**
     * Valores para el texto final: obligatorias sin dato → null (bloquean); opcionales → por defecto.
     *
     * @return array{valores: array<string, string>, faltantes: list<string>}
     */
    public static function resolver(array $valores, array $usadas): array
    {
        $definiciones = self::definiciones();
        $resueltos = [];
        $faltantes = [];

        foreach ($usadas as $id) {
            if (! isset($definiciones[$id])) {
                $faltantes[] = "Variable desconocida «{$id}» (vuelva a insertarla desde la lista)";

                continue;
            }

            [$etiqueta, $obligatoria, $porDefecto] = $definiciones[$id];
            $valor = trim((string) ($valores[$id] ?? ''));

            if ($valor === '' && $obligatoria) {
                $faltantes[] = $etiqueta;
                $resueltos[$id] = "[FALTA: {$etiqueta}]";
            } else {
                $resueltos[$id] = $valor !== '' ? $valor : $porDefecto;
            }
        }

        return ['valores' => $resueltos, 'faltantes' => array_values(array_unique($faltantes))];
    }

    /** Ids de las variables usadas en un contenido HTML del editor. */
    public static function usadas(string $html): array
    {
        preg_match_all('/<span\b[^>]*data-type="mergeTag"[^>]*>/i', $html, $etiquetas);
        $ids = [];

        foreach ($etiquetas[0] as $etiqueta) {
            if (preg_match('/data-id="([^"]+)"/i', $etiqueta, $m)) {
                $ids[] = html_entity_decode($m[1]);
            }
        }

        return array_values(array_unique($ids));
    }

    // Formatos

    public static function fecha(?\DateTimeInterface $fecha): ?string
    {
        return $fecha ? Carbon::instance($fecha)->locale('es')->translatedFormat('j \d\e F \d\e Y') : null;
    }

    private static function hora(?string $hora): ?string
    {
        if (blank($hora)) {
            return null;
        }

        $h = Carbon::createFromFormat(strlen($hora) > 5 ? 'H:i:s' : 'H:i', $hora);

        return $h->format('g:i').($h->hour < 12 ? ' a. m.' : ' p. m.');
    }

    private static function horasEntre(?string $inicio, ?string $fin): ?string
    {
        if (blank($inicio) || blank($fin)) {
            return null;
        }

        $a = Carbon::createFromFormat(strlen($inicio) > 5 ? 'H:i:s' : 'H:i', $inicio);
        $b = Carbon::createFromFormat(strlen($fin) > 5 ? 'H:i:s' : 'H:i', $fin);
        $minutos = $a->diffInMinutes($b, false);

        if ($minutos <= 0) {
            $minutos += 24 * 60; // termina al día siguiente
        }

        $horas = round($minutos / 60, 1);
        $texto = rtrim(rtrim(number_format($horas, 1, ',', '.'), '0'), ',');

        return $texto.($horas == 1 ? ' hora' : ' horas');
    }

    private static function municipio(?string $nombre): ?string
    {
        return $nombre ? mb_convert_case(mb_strtolower($nombre), MB_CASE_TITLE) : null;
    }

    private static function numeroDocumento(?string $documento): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $documento);

        return $digitos !== '' && $digitos === (string) $documento
            ? number_format((int) $digitos, 0, ',', '.')
            : ($documento ?: null);
    }

    private static function identificacionCliente(Contrato $contrato): ?string
    {
        $cliente = $contrato->cliente;

        if (! $cliente?->documento) {
            return null;
        }

        if ($cliente->dv !== null) {
            return 'NIT '.Nit::formatear($cliente->documento, $cliente->dv);
        }

        $tipo = $cliente->tipoIdentificacion?->codigo ?? 'C.C.';
        $tipo = $tipo === 'CC' ? 'C.C.' : $tipo;
        $texto = "{$tipo} No. ".self::numeroDocumento($cliente->documento);

        return $cliente->expedicionMunicipio
            ? $texto.' expedida en '.self::municipio($cliente->expedicionMunicipio->nombre)
            : $texto;
    }

    private static function formaPago(Contrato $contrato): ?string
    {
        return match ($contrato->forma_pago) {
            FormaPago::Contado => 'de contado, antes de iniciar el servicio',
            FormaPago::Credito => $contrato->fecha_limite_pago
                ? 'a crédito, a más tardar el '.self::fecha($contrato->fecha_limite_pago)
                : 'a crédito, en la fecha acordada entre las partes',
            default => null,
        };
    }
}
