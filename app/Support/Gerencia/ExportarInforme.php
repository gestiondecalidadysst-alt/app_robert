<?php

namespace App\Support\Gerencia;

use App\Models\Contrato;
use App\Models\Fuec;
use App\Models\Inspeccion;
use App\Models\Mantenimiento;
use App\Models\PagoContrato;
use App\Models\Servicio;
use App\Support\Pdf\PdfTablaInforme;
use Illuminate\Support\Carbon;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Informes de gerencia en Excel (los de la app anterior, mejorados). Todos por empresa y rango de
 * fechas; la oficina filtra los que la tienen (pagos, contratos y extractos).
 */
final class ExportarInforme
{
    public const INFORMES = [
        'pagos' => 'Pagos recibidos (recibos de caja)',
        'contratos' => 'Estado de contratos (valor, abonado, saldo y vencimiento)',
        'extractos' => 'Extractos (FUEC) expedidos',
        'operacion' => 'Operación de la flota (servicios por día)',
        'preoperacionales' => 'Preoperacionales e inspecciones',
        'mantenimientos' => 'Mantenimientos',
    ];

    /** Informes que pertenecen al módulo de operación (servicios y extractos). */
    public const DE_OPERACION = ['operacion', 'extractos'];

    /**
     * Datos de un informe (columnas + filas), en un solo sitio para que los compartan el
     * Excel y el PDF. La primera fila que arma cada informe son sus encabezados.
     *
     * @return array{titulo: string, columnas: list<string>, filas: list<list<mixed>>}
     */
    public static function conjunto(string $informe, int $empresaId, Carbon $desde, Carbon $hasta, ?int $oficinaId = null): array
    {
        abort_unless(isset(self::INFORMES[$informe]), 404);

        $filas = [];
        $fila = function (array $v) use (&$filas): void {
            $filas[] = $v;
        };
        $rango = [$desde->toDateString(), $hasta->toDateString()];
        $oficina = fn ($q, string $col = 'oficina_id') => $q->when($oficinaId, fn ($q) => $q->where($col, $oficinaId));

        match ($informe) {
            'pagos' => (function () use ($fila, $empresaId, $rango, $oficinaId) {
                $fila(['Recibo', 'Fecha', 'Contrato', 'Cliente', 'Tipo', 'Método', 'Referencia', 'Valor', 'Saldo después', 'Estado', 'Registró']);
                PagoContrato::query()->deEmpresa($empresaId)->whereDate('fecha', '>=', $rango[0])->whereDate('fecha', '<=', $rango[1])
                    ->when($oficinaId, fn ($q) => $q->whereHas('contrato', fn ($c) => $c->where('oficina_id', $oficinaId)))
                    ->with(['contrato.cliente', 'usuario'])->orderBy('fecha')->orderBy('numero')->get()
                    ->each(fn (PagoContrato $p) => $fila([
                        $p->numero_recibo, $p->fecha->format('Y-m-d'), $p->contrato?->numero_formateado, $p->contrato?->cliente?->nombre,
                        $p->anulado() ? 'Anulado' : $p->tipoEtiqueta(), $p->metodo?->getLabel(), $p->referencia,
                        (float) $p->valor, $p->anulado() ? null : (float) $p->saldo_despues,
                        $p->anulado() ? 'Anulado: '.$p->motivo_anulacion : 'Vigente', $p->usuario?->name,
                    ]));
            })(),
            'contratos' => (function () use ($fila, $empresaId, $rango, $oficina) {
                $fila(['Contrato', 'Elaborado', 'Cliente', 'Objeto', 'Inicio', 'Fin', 'Valor', 'Pagado', 'Saldo', 'Fecha límite de pago', 'Días vencido', 'Estado']);
                $oficina(Contrato::query()->deEmpresa($empresaId))
                    ->whereDate('fecha_inicio', '<=', $rango[1])->whereDate('fecha_fin', '>=', $rango[0])
                    ->with('cliente')->withSum(['pagos' => fn ($q) => $q->whereNull('anulado_at')], 'valor')->orderBy('numero')->get()
                    ->each(function (Contrato $c) use ($fila) {
                        $vence = $c->fecha_limite_pago ?? $c->fecha_fin;
                        $dias = $c->saldo() > 0 && $vence ? max(0, (int) $vence->diffInDays(today(), false)) : 0;
                        $fila([
                            $c->numero_formateado, $c->created_at->format('Y-m-d'), $c->cliente?->nombre, $c->objeto?->getLabel(),
                            $c->fecha_inicio->format('Y-m-d'), $c->fecha_fin->format('Y-m-d'), (float) $c->valor, $c->totalPagado(), $c->saldo(),
                            $c->fecha_limite_pago?->format('Y-m-d'), $dias ?: null, $c->estado()->getLabel(),
                        ]);
                    });
            })(),
            'extractos' => (function () use ($fila, $empresaId, $desde, $hasta, $oficina) {
                $fila(['FUEC', 'Expedido', 'Contrato', 'Cliente', 'Placa', 'Conductores', 'Desde', 'Hasta', 'Horario', 'Origen', 'Destino', 'Estado', 'Expidió']);
                $oficina(Fuec::query()->deEmpresa($empresaId))->whereBetween('created_at', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()])
                    ->with(['contrato.cliente', 'conductores', 'origen', 'destino', 'usuario'])->orderBy('created_at')->get()
                    ->each(fn (Fuec $f) => $fila([
                        $f->numero_formateado, $f->created_at->timezone('America/Bogota')->format('Y-m-d H:i'), $f->contrato?->numero_formateado,
                        $f->contrato?->cliente?->nombre, $f->placa, $f->conductores->map->nombre_completo->implode(', '),
                        $f->fecha_inicio->format('Y-m-d'), $f->fecha_fin->format('Y-m-d'), $f->horario()->franjaTexto(),
                        $f->origen?->nombre, $f->destino?->nombre, $f->anulado() ? 'Anulado: '.$f->motivo_anulacion : $f->estado()->getLabel(),
                        $f->usuario?->name,
                    ]));
            })(),
            'operacion' => (function () use ($fila, $empresaId, $desde, $hasta) {
                $fila(['Fecha', 'Horario', 'Placa', 'Conductores', 'Origen', 'Destino', 'Rutograma', 'Cliente', 'Estado', 'FUEC', 'Plan leído', 'Km inicial', 'Km final']);
                $servicios = Servicio::query()->deEmpresa($empresaId)->entre($desde, $hasta)
                    ->with(['vehiculo', 'conductores', 'origen', 'destino', 'ruta', 'contrato.cliente', 'fuec'])->orderBy('fecha_inicio')->orderBy('hora_salida')->get();
                for ($d = $desde->copy()->startOfDay(); $d->lte($hasta); $d->addDay()) {
                    foreach ($servicios->filter(fn (Servicio $s) => $s->activoEl($d)) as $s) {
                        $fila([
                            $d->format('Y-m-d'), $s->franja, $s->placa, $s->conductores->map->nombre_completo->implode(', '),
                            $s->origen?->nombre, $s->destino?->nombre, $s->ruta?->nombre, $s->contrato?->cliente?->nombre,
                            $s->estado->getLabel(), $s->fuec?->numero_formateado, $s->leido_at ? 'Sí' : 'No', $s->km_inicial, $s->km_final,
                        ]);
                    }
                }
            })(),
            'preoperacionales' => (function () use ($fila, $empresaId, $rango) {
                $fila(['Fecha', 'Tipo', 'Placa', 'Conductor', 'Extracto (FUEC)', 'Contrato', 'Resultado', 'Kilometraje', 'Ítems que no cumplen', 'Observaciones']);
                Inspeccion::query()->deEmpresa($empresaId)->whereDate('fecha', '>=', $rango[0])->whereDate('fecha', '<=', $rango[1])
                    ->with(['vehiculo', 'conductor', 'fuec.contrato'])->orderBy('fecha')->orderBy('id')->get()
                    ->each(fn (Inspeccion $i) => $fila([
                        $i->fecha->format('Y-m-d'), $i->tipo->getLabel(), $i->vehiculo?->placa, $i->conductor?->nombre_completo,
                        $i->numeroExtracto(), $i->numeroContrato(),
                        $i->resultado->getLabel(), $i->kilometraje, collect($i->fallas())->pluck('descripcion')->filter()->implode('; '), $i->observaciones,
                    ]));
            })(),
            'mantenimientos' => (function () use ($fila, $empresaId, $rango) {
                $fila(['Fecha', 'Placa', 'Tipo', 'Acción', 'Rutinas', 'Actividad', 'Establecimiento', 'Kilometraje', 'Valor', 'Factura', 'Estado']);
                Mantenimiento::query()->deEmpresa($empresaId)->reales()->whereDate('fecha', '>=', $rango[0])->whereDate('fecha', '<=', $rango[1])
                    ->with(['vehiculo', 'rutinas'])->orderBy('fecha')->get()
                    ->each(fn (Mantenimiento $m) => $fila([
                        $m->fecha->format('Y-m-d'), $m->vehiculo?->placa, $m->tipo->getLabel(), $m->accion?->getLabel(),
                        $m->rutinas->pluck('nombre')->implode('; '), $m->actividad, $m->taller, $m->kilometraje,
                        $m->valor !== null ? (float) $m->valor : null, $m->factura_numero, 'Vigente',
                    ]));
            })(),
        };

        $columnas = array_shift($filas) ?? [];

        return ['titulo' => self::INFORMES[$informe], 'columnas' => $columnas, 'filas' => array_values($filas)];
    }

    public static function excel(string $informe, int $empresaId, Carbon $desde, Carbon $hasta, ?int $oficinaId = null): string
    {
        $conjunto = self::conjunto($informe, $empresaId, $desde, $hasta, $oficinaId);

        $ruta = tempnam(sys_get_temp_dir(), 'inf').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($ruta);
        $writer->getCurrentSheet()->setName(mb_substr(ucfirst($informe), 0, 30));
        $writer->addRow(Row::fromValues([$conjunto['titulo'].' · '.$desde->format('d/m/Y').' – '.$hasta->format('d/m/Y')]));
        $writer->addRow(Row::fromValues($conjunto['columnas']));

        foreach ($conjunto['filas'] as $f) {
            $writer->addRow(Row::fromValues($f));
        }

        $writer->close();

        return $ruta;
    }

    /** Mismo informe que el Excel, pero en PDF con el aspecto estándar del sistema. */
    public static function pdf(string $informe, int $empresaId, Carbon $desde, Carbon $hasta, ?int $oficinaId = null): string
    {
        $conjunto = self::conjunto($informe, $empresaId, $desde, $hasta, $oficinaId);

        return PdfTablaInforme::generar($empresaId, $conjunto['titulo'], $desde, $hasta, $oficinaId, $conjunto['columnas'], $conjunto['filas']);
    }

    public static function nombre(string $informe, Carbon $desde, Carbon $hasta): string
    {
        return "informe-{$informe}-{$desde->format('Y-m-d')}-a-{$hasta->format('Y-m-d')}.xlsx";
    }
}
