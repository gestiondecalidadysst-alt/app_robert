{{-- Hoja de vida del conductor. Mismo formato que la del vehículo: encabezado y pie fijos,
     bloques que no se parten entre páginas. --}}
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 118px 42px 74px 42px; }
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10.5px; color: #1f2937; }

    header { position: fixed; top: -96px; left: 0; right: 0; height: 84px; }
    .cab { border-bottom: 2px solid #1B2B3A; padding-bottom: 8px; }
    .cab table { width: 100%; }
    .cab .logo { height: 46px; }
    .cab .marca { font-size: 15px; font-weight: bold; color: #1B2B3A; }
    .cab .sub { font-size: 10px; color: #6b7280; }
    .cab .titulo { text-align: right; }
    .cab .titulo .t { font-size: 13px; font-weight: bold; color: #C2410C; }
    .cab .titulo .n { font-size: 16px; font-weight: bold; }

    footer { position: fixed; bottom: -58px; left: 0; right: 0; height: 46px;
             border-top: 1px solid #e5e7eb; padding-top: 6px; font-size: 8px; color: #6b7280; }
    footer table { width: 100%; }
    footer .pagina:after { content: "Página " counter(page) " de " counter(pages); }

    h2 { font-size: 12px; color: #1B2B3A; margin: 18px 0 8px; padding: 5px 8px;
         background: #F3F5F8; border-left: 4px solid #C2410C; page-break-after: avoid; }
    .seccion { page-break-inside: avoid; }

    table.datos { width: 100%; border-collapse: collapse; }
    table.datos td { padding: 4px 8px; border-bottom: 1px solid #eef1f4; vertical-align: top; }
    table.datos td.k { color: #6b7280; width: 26%; }
    table.datos td.v { font-weight: bold; }

    table.lista { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
    table.lista th { background: #1B2B3A; color: #fff; font-size: 9px; text-align: left; padding: 5px 7px; }
    table.lista td { padding: 5px 7px; border-bottom: 1px solid #eef1f4; font-size: 9.5px; vertical-align: top; }
    table.lista tr { page-break-inside: avoid; }
    table.lista tr:nth-child(even) td { background: #fafbfc; }

    .vencido { color: #b91c1c; font-weight: bold; }
    .ok { color: #047857; }
    .vacio { color: #9ca3af; font-style: italic; padding: 6px 2px; }
    .pill { display: inline-block; padding: 1px 7px; border-radius: 8px; font-size: 8.5px; background: #eef1f4; }
</style>
</head>
<body>

<header>
    <div class="cab">
        <table>
            <tr>
                <td style="width: 60%">
                    @if ($logo)<img src="{{ $logo }}" class="logo"><br>@endif
                    <span class="marca">{{ $empresa->nombre_comercial ?: $empresa->razon_social }}</span>
                    <div class="sub">NIT {{ $empresa->nit }}{{ $empresa->dv ? '-'.$empresa->dv : '' }}</div>
                </td>
                <td class="titulo">
                    <div class="t">Hoja de vida del conductor</div>
                    <div class="n">{{ $c->nombre_completo }}</div>
                    <div class="sub">{{ $c->tipoIdentificacion?->codigo ?? 'CC' }} {{ $c->documento }}</div>
                </td>
            </tr>
        </table>
    </div>
</header>

<footer>
    <table>
        <tr>
            <td>Generado el {{ now()->timezone(config('app.timezone'))->format('d/m/Y h:i a') }} · Rango: {{ $desde->format('d/m/Y') }} – {{ $hasta->format('d/m/Y') }}</td>
            <td style="text-align: right" class="pagina"></td>
        </tr>
    </table>
</footer>

<main>
    @foreach ($secciones as $clave)
        @if ($clave === 'datos')
            <div class="seccion">
                <h2>Datos generales</h2>
                <table class="datos">
                    <tr><td class="k">Nombre</td><td class="v">{{ $c->nombre_completo }}</td><td class="k">Documento</td><td class="v">{{ $c->tipoIdentificacion?->codigo ?? 'CC' }} {{ $c->documento }}</td></tr>
                    <tr><td class="k">Fecha de nacimiento</td><td class="v">{{ $c->fecha_nacimiento?->format('d/m/Y') ?: '—' }}</td><td class="k">Grupo sanguíneo</td><td class="v">{{ $c->grupo_sanguineo ?: '—' }}</td></tr>
                    <tr><td class="k">Teléfono</td><td class="v">{{ $c->telefono ?: '—' }}</td><td class="k">Correo</td><td class="v">{{ $c->email ?: '—' }}</td></tr>
                    <tr><td class="k">Dirección</td><td class="v">{{ $c->direccion ?: '—' }}</td><td class="k">Municipio</td><td class="v">{{ $c->municipio?->nombre ?: '—' }}</td></tr>
                    <tr><td class="k">EPS</td><td class="v">{{ $c->eps?->nombre ?: '—' }}</td><td class="k">ARL</td><td class="v">{{ $c->arl?->nombre ?: '—' }}</td></tr>
                    <tr><td class="k">Fondo de pensiones</td><td class="v">{{ $c->fondo_pensiones ?: '—' }}</td><td class="k">Nivel académico</td><td class="v">{{ $c->nivelAcademico?->nombre ?: '—' }}</td></tr>
                    <tr><td class="k">Oficina</td><td class="v">{{ $c->oficina?->nombre ?: '—' }}</td><td class="k">Ingreso</td><td class="v">{{ $c->fecha_ingreso?->format('d/m/Y') ?: '—' }}</td></tr>
                    <tr><td class="k">Contacto de emergencia</td><td class="v">{{ $c->contacto_emergencia ?: '—' }}</td><td class="k">Teléfono emergencia</td><td class="v">{{ $c->telefono_emergencia ?: '—' }}</td></tr>
                </table>
            </div>
        @endif

        @if ($clave === 'documentos')
            <h2>Documentos y licencia</h2>
            <table class="lista">
                <tr><th style="width: 24%">Documento</th><th style="width: 15%">Número</th><th>Categoría / entidad</th><th style="width: 16%">Expedición</th><th style="width: 16%">Vencimiento</th></tr>
                @forelse ($datos['documentos'] as $doc)
                    <tr>
                        <td>{{ $doc->tipo?->getLabel() }}</td>
                        <td>{{ $doc->numero ?: '—' }}</td>
                        <td>{{ $doc->categoriaLicencia?->nombre ?: ($doc->entidad ?: '—') }}</td>
                        <td>{{ $doc->fecha_expedicion?->format('d/m/Y') ?: '—' }}</td>
                        <td class="{{ $doc->fecha_vencimiento && $doc->fecha_vencimiento->isPast() ? 'vencido' : 'ok' }}">
                            {{ $doc->fecha_vencimiento?->format('d/m/Y') ?: '—' }}
                            {{ $doc->fecha_vencimiento && $doc->fecha_vencimiento->isPast() ? '(vencido)' : '' }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="vacio">Sin documentos registrados.</td></tr>
                @endforelse
            </table>
        @endif

        @if ($clave === 'vehiculos')
            <h2>Vehículos que ha conducido</h2>
            <table class="lista">
                <tr><th>Placa</th><th>Marca / línea</th><th style="width: 16%">Desde</th><th style="width: 16%">Hasta</th><th style="width: 12%">Principal</th></tr>
                @forelse ($datos['vehiculos'] as $a)
                    <tr>
                        <td>{{ $a->vehiculo?->placa ?: '—' }}</td>
                        <td>{{ $a->vehiculo?->marca?->nombre }} {{ $a->vehiculo?->linea?->nombre }}</td>
                        <td>{{ $a->fecha_desde?->format('d/m/Y') }}</td>
                        <td>{{ $a->fecha_hasta?->format('d/m/Y') ?: 'Vigente' }}</td>
                        <td>{{ $a->principal ? 'Sí' : 'No' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="vacio">Sin vehículos asignados.</td></tr>
                @endforelse
            </table>
        @endif

        @if ($clave === 'servicios')
            <h2>Servicios prestados <span class="pill">{{ $desde->format('d/m/Y') }} – {{ $hasta->format('d/m/Y') }}</span></h2>
            <table class="lista">
                <tr><th style="width: 14%">Fecha</th><th>Contrato</th><th>Ruta</th><th>Vehículo</th><th style="width: 14%">Estado</th></tr>
                @forelse ($datos['servicios'] as $s)
                    <tr>
                        <td>{{ $s->fecha_inicio?->format('d/m/Y') }}</td>
                        <td>{{ $s->contrato?->numero ?: '—' }}</td>
                        <td>{{ $s->ruta?->nombre ?: '—' }}</td>
                        <td>{{ $s->vehiculo?->placa ?: '—' }}</td>
                        <td>{{ $s->estado?->getLabel() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="vacio">Sin servicios en el rango.</td></tr>
                @endforelse
            </table>
        @endif

        @if ($clave === 'preoperacionales')
            <h2>Preoperacionales firmados <span class="pill">{{ $desde->format('d/m/Y') }} – {{ $hasta->format('d/m/Y') }}</span></h2>
            <table class="lista">
                <tr><th style="width: 12%">Fecha</th><th>Vehículo</th><th>Extracto (FUEC)</th><th>Contrato</th><th style="width: 12%">Resultado</th></tr>
                @forelse ($datos['preoperacionales'] as $i)
                    <tr>
                        <td>{{ $i->fecha?->format('d/m/Y') }}</td>
                        <td>{{ $i->placa ?: $i->vehiculo?->placa ?: '—' }}</td>
                        <td>{{ $i->numeroExtracto() ?: '—' }}</td>
                        <td>{{ $i->numeroContrato() ?: '—' }}</td>
                        <td class="{{ $i->apta() ? 'ok' : 'vencido' }}">{{ $i->resultado?->getLabel() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="vacio">Sin preoperacionales en el rango.</td></tr>
                @endforelse
            </table>
        @endif

        @if ($clave === 'novedades')
            <h2>Novedades <span class="pill">{{ $desde->format('d/m/Y') }} – {{ $hasta->format('d/m/Y') }}</span></h2>
            <table class="lista">
                <tr><th style="width: 14%">Fecha</th><th>Tipo</th><th>Cambio de estado</th><th>Observación</th></tr>
                @forelse ($datos['novedades'] as $n)
                    <tr>
                        <td>{{ $n->fecha?->format('d/m/Y') }}</td>
                        <td>{{ $n->tipo }}</td>
                        <td>{{ $n->estado_anterior }} → {{ $n->estado_nuevo }}</td>
                        <td>{{ $n->observacion ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="vacio">Sin novedades en el rango.</td></tr>
                @endforelse
            </table>
        @endif
    @endforeach
</main>

</body>
</html>
