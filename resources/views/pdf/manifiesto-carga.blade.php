{{--
    Manifiesto electrónico de carga en el formato del Ministerio de Transporte (horizontal, carta).
    Lo arma App\Support\Pdf\PdfManifiestoCarga. Lleva en la esquina superior derecha el QR de
    verificación pública y el número de autorización del RNDC cuando ya se envió.
--}}
@php
    $p = fn ($v) => '$ ' . number_format((float) $v, 0, ',', '.');
    $fecha = fn ($d) => $d ? $d->format('Y/m/d') : '';
    $tipo = \App\Models\ManifiestoCarga::TIPOS[$m->tipo] ?? $m->tipo;
    $resp = fn ($c) => \App\Models\ManifiestoCarga::RESPONSABLES_PAGO[$c] ?? '—';
    $trayectos = is_array($m->trayectos_vacio) ? $m->trayectos_vacio : [];
    $trayectoVacio = function (int $i) use ($trayectos) {
        $t = $trayectos[$i] ?? null;
        if (! $t) return ['origen' => '', 'destino' => ''];
        $o = $t['origen_id'] ?? null ? \App\Models\Municipio::find($t['origen_id'])?->nombre_completo : '';
        $d = $t['destino_id'] ?? null ? \App\Models\Municipio::find($t['destino_id'])?->nombre_completo : '';
        return ['origen' => (string) $o, 'destino' => (string) $d];
    };
    $tv1 = $trayectoVacio(0); $tv2 = $trayectoVacio(1);
    $radicado = $m->ultimoRegistroRndc?->ingresoid;
    $titulo = $titular instanceof \App\Models\Propietario ? trim($titular->nombres . ' ' . $titular->apellidos) : ($titular?->nombre_completo ?? '—');
    $titularDoc = $titular?->documento ?? '';
    $titularDir = $titular?->direccion ?? '';
    $titularMuni = $titular?->municipio?->nombre_completo ?? '';
    $titularTel = $titular?->telefono ?? '';
    $c1 = $m->conductor; $c2 = $m->conductor2;
    $direccionCond = fn ($c) => $c?->direccion ?? '';
    $muniCond = fn ($c) => $c?->municipio?->nombre_completo ?? '';
    $licCond = function ($c) {
        if (! $c) return '';
        $lic = $c->licencia();
        return $lic ? (($lic->categoriaLicencia?->codigo ?? '') . '-' . $lic->numero) : $c->documento;
    };
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Manifiesto {{ $m->numeroFormateado() }}</title>
<style>
    @page { margin: 18px 22px 22px 22px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 7.4px; color: #111; line-height: 1.25; }
    table { width: 100%; border-collapse: collapse; }
    td, th { vertical-align: top; }
    .b { border: 0.6px solid #000; }
    .bt { border-top: 0.6px solid #000; } .bb { border-bottom: 0.6px solid #000; }
    .bl { border-left: 0.6px solid #000; } .br { border-right: 0.6px solid #000; }
    .p3 { padding: 2px 4px; } .p4 { padding: 3px 5px; }
    .et { font-size: 6px; color: #333; letter-spacing: .3px; text-transform: uppercase; }
    .val { font-weight: bold; font-size: 7.6px; }
    .cent { text-align: center; } .der { text-align: right; }
    .tit { font-weight: bold; font-size: 11px; text-align: center; line-height: 1.1; }
    .sec { background: #e2e8f0; font-weight: bold; font-size: 7.6px; padding: 3px 5px; border: 0.6px solid #000; text-align: center; }
    .legal { font-size: 6px; color: #333; line-height: 1.3; }
    .anulado { background: #fef2f2; border: 2px solid #b91c1c; color: #7f1d1d; font-weight: bold; text-align: center; padding: 4px; margin-bottom: 4px; }
    .obs { height: 70px; }
    .firma { height: 48px; }
</style>
</head>
<body>

@if ($m->anulado())
    <div class="anulado">ANULADO el {{ $fecha($m->anulado_at) }}{{ $m->motivo_anulacion === 'D' ? ' · Error en la digitación' : ($m->motivo_anulacion === 'S' ? ' · Cancelación del servicio' : '') }}</div>
@endif

{{-- ENCABEZADO: logos en texto, título central, texto legal y bloque de autorización con QR --}}
<table class="b">
    <tr>
        <td class="p4" style="width: 14%; text-align: center; border-right: 0.6px solid #000;">
            @if ($logo)<img src="{{ $logo }}" style="max-height: 38px; max-width: 110px">@endif
            <div style="font-weight: bold; font-size: 8px; margin-top: 2px">MINISTERIO DE</div>
            <div style="font-weight: bold; font-size: 8px">TRANSPORTE</div>
            <div class="et" style="margin-top: 2px">SuperTransporte</div>
        </td>
        <td class="p4" style="width: 44%; border-right: 0.6px solid #000;">
            <div class="tit">MANIFIESTO ELECTRÓNICO DE CARGA</div>
            <div class="tit">MINISTERIO DE TRANSPORTE</div>
            <table style="margin-top: 6px">
                <tr>
                    <td style="width: 35%"><span class="et">NIT:</span> <span class="val">8999990554</span></td>
                    <td><span class="et">Tel:</span> <span class="val">MINTRANSPORTE</span></td>
                </tr>
                <tr><td colspan="2"><span class="val">BOGOTÁ D. C.</span></td></tr>
            </table>
        </td>
        <td class="p4" style="width: 28%; border-right: 0.6px solid #000;">
            <div class="legal">
                La impresión en soporte cartular (papel) de este acto administrativo producido por medios
                electrónicos en cumplimiento de la ley 527 de 1999 (artículos 6 al 13) y de la ley 962 de 2005
                (artículos 6), es una reproducción del documento original que se encuentra en formato electrónico
                en la base de datos del RNDC en el Ministerio de Transporte, cuya representación digital goza
                de autenticidad, integridad y no repudio.
            </div>
            <div style="margin-top: 4px">
                <span class="et">Manifiesto No.:</span> <span class="val">{{ $m->numeroFormateado() }}</span><br>
                <span class="et">Autorización:</span> <span class="val">{{ $radicado ?: 'PENDIENTE' }}</span>
            </div>
        </td>
        <td class="p4" style="width: 14%; text-align: center;">
            <img src="{{ $qr }}" alt="QR" style="width: 78px; height: 78px">
            <div class="et" style="margin-top: 2px">Verifique</div>
        </td>
    </tr>
    <tr>
        <td class="p3 bt" colspan="4">
            <table>
                <tr>
                    <td style="width: 12%"><span class="et">Fecha hora radicación</span><br><span class="val">{{ $fecha($radicado ? $m->ultimoRegistroRndc?->created_at : $m->created_at) }} {{ ($radicado ? $m->ultimoRegistroRndc?->created_at : $m->created_at)?->format('H:i') }}</span></td>
                    <td style="width: 22%; border-left: 0.6px solid #000; padding-left: 5px"><span class="et">Fecha de expedición</span><br><span class="val">{{ $fecha($m->fecha_expedicion) }}</span></td>
                    <td style="width: 14%; border-left: 0.6px solid #000; padding-left: 5px"><span class="et">Tipo de manifiesto</span><br><span class="val">{{ $tipo }}</span></td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td class="p3 bt" colspan="4">
            <table>
                <tr>
                    <td style="width: 25%"><span class="et">Origen del viaje</span><br><span class="val">{{ $m->origen?->nombre_completo }}</span></td>
                    <td style="width: 25%; border-left: 0.6px solid #000; padding-left: 5px"><span class="et">Municipio intermedio</span><br><span class="val">{{ $m->municipioIntermedio?->nombre_completo ?: '—' }}</span></td>
                    <td style="width: 50%; border-left: 0.6px solid #000; padding-left: 5px"><span class="et">Destino del viaje</span><br><span class="val">{{ $m->destino?->nombre_completo }}</span></td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td class="p3 bt" colspan="4">
            <table>
                <tr>
                    <td style="width: 25%"><span class="et">Origen trayecto en vacío 1</span><br><span class="val">{{ $tv1['origen'] ?: '—' }}</span></td>
                    <td style="width: 25%; border-left: 0.6px solid #000; padding-left: 5px"><span class="et">Destino trayecto en vacío 1</span><br><span class="val">{{ $tv1['destino'] ?: '—' }}</span></td>
                    <td style="width: 25%; border-left: 0.6px solid #000; padding-left: 5px"><span class="et">Origen trayecto en vacío 2</span><br><span class="val">{{ $tv2['origen'] ?: '—' }}</span></td>
                    <td style="width: 25%; border-left: 0.6px solid #000; padding-left: 5px"><span class="et">Destino trayecto en vacío 2</span><br><span class="val">{{ $tv2['destino'] ?: '—' }}</span></td>
                </tr>
            </table>
        </td>
    </tr>
    @if ($m->via_utilizar)
        <tr><td class="p3 bt" colspan="4"><span class="et">Vía a utilizar</span> <span style="font-size: 7px">{{ $m->via_utilizar }}</span></td></tr>
    @endif
</table>

{{-- BLOQUE: TITULAR, VEHICULO Y CONDUCTORES --}}
<div class="sec" style="border-top: none">INFORMACIÓN DEL TITULAR, VEHÍCULO Y CONDUCTORES</div>
<table class="b" style="border-top: none">
    <tr>
        <td class="p3" style="width: 28%"><span class="et">Titular del manifiesto</span><br><span class="val">{{ $titulo }}</span></td>
        <td class="p3 bl" style="width: 14%"><span class="et">Documento</span><br><span class="val">{{ $titularDoc }}</span></td>
        <td class="p3 bl" style="width: 24%"><span class="et">Dirección</span><br><span class="val">{{ $titularDir }}</span></td>
        <td class="p3 bl" style="width: 14%"><span class="et">Teléfonos</span><br><span class="val">{{ $titularTel }}</span></td>
        <td class="p3 bl" style="width: 20%"><span class="et">Ciudad</span><br><span class="val">{{ $titularMuni }}</span></td>
    </tr>
    <tr>
        <td class="p3 bt"><span class="et">Placa</span><br><span class="val">{{ $m->vehiculo?->placa ?? '—' }}</span></td>
        <td class="p3 bt bl"><span class="et">Marca</span><br><span class="val">{{ $m->vehiculo?->marca?->nombre ?? '—' }}</span></td>
        <td class="p3 bt bl"><span class="et">Placa semirremolque</span><br><span class="val">{{ $m->remolque?->placa ?? '—' }}</span></td>
        <td class="p3 bt bl"><span class="et">Placa semirremolque 2</span><br><span class="val">{{ $m->remolque2?->placa ?? '—' }}</span></td>
        <td class="p3 bt bl">
            <table>
                <tr>
                    <td style="width: 34%"><span class="et">Configuración</span><br><span class="val">{{ $m->vehiculo?->rndc_configuracion ? substr(\App\Support\Carga\CatalogoRndc::CONFIGURACIONES_VEHICULO[$m->vehiculo->rndc_configuracion] ?? '', -3) : '—' }}</span></td>
                    <td style="width: 33%"><span class="et">PesoVacio</span><br><span class="val">{{ $m->vehiculo?->peso_vacio ? number_format($m->vehiculo->peso_vacio, 0, ',', '.') : '—' }}</span></td>
                    <td style="width: 33%"><span class="et">Peso Remolque</span><br><span class="val">{{ $m->remolque?->peso_vacio ? number_format($m->remolque->peso_vacio, 0, ',', '.') : '—' }}</span></td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td class="p3 bt" colspan="4"><span class="et">Compañía seguros SOAT</span><br><span class="val">{{ $soat?->aseguradora?->nit }} {{ $soat?->aseguradora?->nombre ?: '—' }}</span></td>
        <td class="p3 bt bl">
            <table>
                <tr>
                    <td style="width: 60%"><span class="et">No. Póliza</span><br><span class="val">{{ $soat?->numero ?: '—' }}</span></td>
                    <td style="width: 40%"><span class="et">F.Vencimiento</span><br><span class="val">{{ $fecha($soat?->fecha_vencimiento) ?: '—' }}</span></td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td class="p3 bt"><span class="et">Conductor</span><br><span class="val">{{ $c1 ? trim($c1->nombres . ' ' . $c1->apellidos) : '—' }}</span></td>
        <td class="p3 bt bl"><span class="et">Documento</span><br><span class="val">{{ $c1?->documento ?? '—' }}</span></td>
        <td class="p3 bt bl"><span class="et">Dirección</span><br><span class="val">{{ $direccionCond($c1) }}</span></td>
        <td class="p3 bt bl"><span class="et">Teléfonos</span><br><span class="val">{{ $c1?->telefono }}</span></td>
        <td class="p3 bt bl">
            <table>
                <tr>
                    <td style="width: 55%"><span class="et">No. Licencia</span><br><span class="val">{{ $licCond($c1) }}</span></td>
                    <td style="width: 45%"><span class="et">Ciudad</span><br><span class="val">{{ $muniCond($c1) }}</span></td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td class="p3 bt"><span class="et">Conductor No. 2</span><br><span class="val">{{ $c2 ? trim($c2->nombres . ' ' . $c2->apellidos) : '—' }}</span></td>
        <td class="p3 bt bl"><span class="et">Documento</span><br><span class="val">{{ $c2?->documento ?? '—' }}</span></td>
        <td class="p3 bt bl"><span class="et">Dirección conductor 2</span><br><span class="val">{{ $direccionCond($c2) }}</span></td>
        <td class="p3 bt bl"><span class="et">Teléfonos</span><br><span class="val">{{ $c2?->telefono ?? '—' }}</span></td>
        <td class="p3 bt bl">
            <table>
                <tr>
                    <td style="width: 55%"><span class="et">No. Licencia</span><br><span class="val">{{ $licCond($c2) ?: '—' }}</span></td>
                    <td style="width: 45%"><span class="et">Ciudad conductor 2</span><br><span class="val">{{ $muniCond($c2) ?: '—' }}</span></td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- MERCANCIA --}}
<div class="sec" style="border-top: none">INFORMACIÓN DE LA MERCANCÍA TRANSPORTADA</div>
<table class="b" style="border-top: none">
    <tr style="background: #f1f5f9">
        <td class="p3 et bb cent" style="width: 7%; border-right: 0.6px solid #000">Nro. Remesa</td>
        <td class="p3 et bb cent" style="width: 8%; border-right: 0.6px solid #000">Unidad</td>
        <td class="p3 et bb cent der" style="width: 8%; border-right: 0.6px solid #000">Cantidad</td>
        <td class="p3 et bb cent" style="width: 9%; border-right: 0.6px solid #000">Naturaleza</td>
        <td class="p3 et bb cent" style="width: 7%; border-right: 0.6px solid #000">Empaque</td>
        <td class="p3 et bb cent" style="width: 15%; border-right: 0.6px solid #000">Producto</td>
        <td class="p3 et bb cent" style="width: 22%; border-right: 0.6px solid #000">Remitente / Lugar cargue</td>
        <td class="p3 et bb cent" style="width: 20%; border-right: 0.6px solid #000">Destinatario / Lugar descargue</td>
        <td class="p3 et bb cent" style="width: 4%">Dueño póliza</td>
    </tr>
    @forelse ($m->remesas as $r)
        <tr>
            <td class="p3 bt" style="border-right: 0.6px solid #000"><span class="val">{{ $r->numeroFormateado() }}</span></td>
            <td class="p3 bt" style="border-right: 0.6px solid #000">{{ $r->unidad === 2 ? 'Galones' : 'Kilogramos' }}</td>
            <td class="p3 bt der" style="border-right: 0.6px solid #000">{{ $r->cantidad ? number_format($r->cantidad, 2, ',', '.') : '—' }}</td>
            <td class="p3 bt" style="border-right: 0.6px solid #000">{{ \App\Models\RemesaCarga::NATURALEZAS[$r->naturaleza] ?? '' }}</td>
            <td class="p3 bt" style="border-right: 0.6px solid #000">{{ \App\Models\RemesaCarga::OPERACIONES[$r->operacion] ?? '' }}</td>
            <td class="p3 bt" style="border-right: 0.6px solid #000">{{ $r->producto_codigo }}<br>{{ $r->producto_descripcion }}</td>
            <td class="p3 bt" style="border-right: 0.6px solid #000">
                <span class="val">{{ $r->remitente?->documento }} {{ $r->remitente?->nombre_completo }}</span><br>
                {{ $r->remitente?->direccion }}<br>{{ $r->remitente?->municipio?->nombre_completo }}
            </td>
            <td class="p3 bt" style="border-right: 0.6px solid #000">
                <span class="val">{{ $r->destinatario?->documento }} {{ $r->destinatario?->nombre_completo }}</span><br>
                {{ $r->destinatario?->direccion }}<br>{{ $r->destinatario?->municipio?->nombre_completo }}
            </td>
            <td class="p3 bt cent">{{ \App\Models\RemesaCarga::DUENOS_POLIZA[$r->dueno_poliza] ?? '' }}</td>
        </tr>
    @empty
        <tr><td colspan="9" class="p3 bt cent et">Sin remesas asociadas.</td></tr>
    @endforelse
</table>

{{-- VALORES y OBSERVACIONES --}}
<table style="margin-top: 0">
    <tr>
        <td style="width: 58%; padding-right: 3px; vertical-align: top">
            <div class="sec">VALORES</div>
            <table class="b" style="border-top: none">
                <tr>
                    <td class="p3" style="width: 42%"><span class="et">Valor total del viaje</span></td>
                    <td class="p3 der val bl" style="width: 24%">{{ $p($m->valor_flete) }}</td>
                    <td class="p3 bl" rowspan="4" style="width: 34%; vertical-align: top">
                        <table>
                            <tr>
                                <td style="width: 55%"><span class="et">Lugar de pago</span><br><span class="val">{{ $m->municipioPago?->nombre_completo ?? $m->destino?->nombre_completo }}</span></td>
                                <td style="width: 45%" class="bl p3"><span class="et">Fecha</span><br><span class="val">{{ $fecha($m->fecha_pago_saldo ?? $m->fecha_expedicion) }}</span></td>
                            </tr>
                            <tr><td colspan="2" class="bt p3"><span class="et">Cargue pagado por</span><br><span class="val">{{ $resp($m->responsable_cargue) }}</span></td></tr>
                            <tr><td colspan="2" class="bt p3"><span class="et">Descargue pagado por</span><br><span class="val">{{ $resp($m->responsable_descargue) }}</span></td></tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td class="p3 bt"><span class="et">Retención en la fuente ({{ rtrim(rtrim(number_format($m->retencion_fuente_pct, 2), '0'), '.') }} %)</span></td>
                    <td class="p3 bt der bl">{{ $p($retefuente) }}</td>
                </tr>
                <tr>
                    <td class="p3 bt"><span class="et">Retención ICA ({{ rtrim(rtrim(number_format($m->reteica_por_mil, 2), '0'), '.') }} x1000)</span></td>
                    <td class="p3 bt der bl">{{ $p($reteica) }}</td>
                </tr>
                <tr>
                    <td class="p3 bt"><span class="et">Retención FOPAT ({{ rtrim(rtrim(number_format($m->retencion_fopat_pct, 2), '0'), '.') }} x1000)</span></td>
                    <td class="p3 bt der bl">{{ $p($retefopat) }}</td>
                </tr>
                <tr>
                    <td class="p3 bt" style="background: #eef1f5"><span class="et">Valor neto a pagar</span></td>
                    <td class="p3 bt der val bl" style="background: #eef1f5">{{ $p($valor_neto) }}</td>
                    <td class="p3 bt bl" style="background: #eef1f5"><span class="et">Valor anticipo</span><br><span class="val">{{ $p($m->anticipo) }}</span></td>
                </tr>
                <tr>
                    <td class="p3 bt val" style="background: #1B2B3A; color: #fff">SALDO A PAGAR</td>
                    <td class="p3 bt der val bl" style="background: #1B2B3A; color: #fff; font-size: 9px">{{ $p($m->saldo()) }}</td>
                    <td class="p3 bt bl">&nbsp;</td>
                </tr>
                <tr>
                    <td colspan="3" class="p3 bt"><span class="et">Valor total del viaje en letras:</span> <span class="val">{{ $valor_en_letras }}</span></td>
                </tr>
            </table>
        </td>
        <td style="width: 42%; padding-left: 3px; vertical-align: top">
            <div class="sec">OBSERVACIONES</div>
            <div class="b obs p3" style="border-top: none">{{ $m->observaciones }}</div>
        </td>
    </tr>
</table>

{{-- Firmas y pie legal de Supertransporte --}}
<table class="b" style="margin-top: 3px">
    <tr>
        <td class="p3" style="width: 50%; border-right: 0.6px solid #000">
            <div class="firma"></div>
            <div class="cent et bt p3">Firma y huella TITULAR MANIFIESTO o ACEPTACIÓN DIGITAL</div>
        </td>
        <td class="p3" style="width: 50%">
            <div class="firma"></div>
            <div class="cent et bt p3">Firma y huella del CONDUCTOR o ACEPTACIÓN DIGITAL</div>
        </td>
    </tr>
</table>

<div class="legal" style="margin-top: 4px">
    Si se informa de algún fraude o conoce de alguna irregularidad en el Registro Nacional de Despachos de Carga (RNDC),
    denúncielo a la Superintendencia de Puertos y Transporte, en la línea gratuita nacional 018000 915615 o a través
    del correo electrónico: atencionciudadano@supertransporte.gov.co.
</div>
</body>
</html>
