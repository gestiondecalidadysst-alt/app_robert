{{-- Hoja de vida del vehículo. Encabezado y pie fijos; cada ficha y cada fila se marca
     page-break-inside: avoid para no quedar partida entre dos páginas. --}}
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
    .cab .titulo .p { font-size: 18px; font-weight: bold; letter-spacing: 1px; }

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
                    <div class="t">Hoja de vida del vehículo</div>
                    <div class="p">{{ $v->placa }}</div>
                    <div class="sub">{{ $v->marca?->nombre }} {{ $v->linea?->nombre }} · {{ $v->modelo }}</div>
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
                    <tr><td class="k">Placa</td><td class="v">{{ $v->placa }}</td><td class="k">N.º interno</td><td class="v">{{ $v->numero_interno ?: '—' }}</td></tr>
                    <tr><td class="k">Marca / Línea</td><td class="v">{{ $v->marca?->nombre }} {{ $v->linea?->nombre }}</td><td class="k">Modelo</td><td class="v">{{ $v->modelo ?: '—' }}</td></tr>
                    <tr><td class="k">Clase</td><td class="v">{{ $v->claseVehiculo?->nombre ?: '—' }}</td><td class="k">Carrocería</td><td class="v">{{ $v->carroceria?->nombre ?: '—' }}</td></tr>
                    <tr><td class="k">Combustible</td><td class="v">{{ $v->combustible?->nombre ?: '—' }}</td><td class="k">Color</td><td class="v">{{ $v->color ?: '—' }}</td></tr>
                    <tr><td class="k">Tipo de servicio</td><td class="v">{{ $v->tipoServicio?->nombre ?: '—' }}</td><td class="k">Capacidad</td><td class="v">{{ $v->capacidad_pasajeros ? $v->capacidad_pasajeros.' pas.' : ($v->capacidad_kg ? number_format($v->capacidad_kg, 0, ',', '.').' kg' : '—') }}</td></tr>
                    <tr><td class="k">Motor</td><td class="v">{{ $v->numero_motor ?: '—' }}</td><td class="k">Chasis / VIN</td><td class="v">{{ $v->numero_chasis ?: ($v->vin ?: '—') }}</td></tr>
                    <tr><td class="k">Propietario</td><td class="v">{{ $v->propietario?->nombre_completo ?: '—' }}</td><td class="k">Tenedor</td><td class="v">{{ $v->tenedor?->nombre_completo ?: '—' }}</td></tr>
                    <tr><td class="k">Organismo de tránsito</td><td class="v">{{ $v->organismoTransito?->nombre ?: '—' }}</td><td class="k">Estado</td><td class="v">{{ $v->estado?->getLabel() }}</td></tr>
                    <tr><td class="k">Matrícula</td><td class="v">{{ $v->fecha_matricula?->format('d/m/Y') ?: '—' }}</td><td class="k">Kilometraje</td><td class="v">{{ $v->kilometrajeActual() !== null ? number_format($v->kilometrajeActual(), 0, ',', '.').' km' : '—' }}</td></tr>
                </table>
            </div>
        @endif

        @if ($clave === 'documentos')
            <h2>Documentos y vencimientos</h2>
            @forelse ($datos['documentos'] as $doc)
                <table class="lista"><tr>
                    <th style="width: 26%">Documento</th><th style="width: 18%">Número</th>
                    <th style="width: 20%">Entidad</th><th style="width: 18%">Expedición</th><th style="width: 18%">Vencimiento</th>
                </tr>
                <tr>
                    <td>{{ $doc->tipo?->getLabel() }}</td>
                    <td>{{ $doc->numero ?: '—' }}</td>
                    <td>{{ $doc->aseguradora?->nombre ?: ($doc->entidad ?: '—') }}</td>
                    <td>{{ $doc->fecha_expedicion?->format('d/m/Y') ?: '—' }}</td>
                    <td class="{{ $doc->fecha_vencimiento && $doc->fecha_vencimiento->isPast() ? 'vencido' : 'ok' }}">
                        {{ $doc->fecha_vencimiento?->format('d/m/Y') ?: '—' }}
                        {{ $doc->fecha_vencimiento && $doc->fecha_vencimiento->isPast() ? '(vencido)' : '' }}
                    </td>
                </tr></table>
            @empty
                <div class="vacio">Sin documentos registrados.</div>
            @endforelse
        @endif

        @if ($clave === 'conductores')
            <h2>Conductores que lo han conducido</h2>
            <table class="lista">
                <tr><th>Conductor</th><th>Documento</th><th style="width: 16%">Desde</th><th style="width: 16%">Hasta</th><th style="width: 12%">Principal</th></tr>
                @forelse ($datos['conductores'] as $a)
                    <tr>
                        <td>{{ $a->conductor?->nombre_completo ?: '—' }}</td>
                        <td>{{ $a->conductor?->documento ?: '—' }}</td>
                        <td>{{ $a->fecha_desde?->format('d/m/Y') }}</td>
                        <td>{{ $a->fecha_hasta?->format('d/m/Y') ?: 'Vigente' }}</td>
                        <td>{{ $a->principal ? 'Sí' : 'No' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="vacio">Sin conductores asignados.</td></tr>
                @endforelse
            </table>
        @endif

        @if ($clave === 'servicios')
            <h2>Servicios prestados <span class="pill">{{ $desde->format('d/m/Y') }} – {{ $hasta->format('d/m/Y') }}</span></h2>
            <table class="lista">
                <tr><th style="width: 14%">Fecha</th><th>Contrato</th><th>Ruta</th><th>Conductor(es)</th><th style="width: 14%">Estado</th></tr>
                @forelse ($datos['servicios'] as $s)
                    <tr>
                        <td>{{ $s->fecha_inicio?->format('d/m/Y') }}</td>
                        <td>{{ $s->contrato?->numero ?: '—' }}</td>
                        <td>{{ $s->ruta?->nombre ?: '—' }}</td>
                        <td>{{ $s->conductores->pluck('nombre_completo')->implode(', ') ?: '—' }}</td>
                        <td>{{ $s->estado?->getLabel() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="vacio">Sin servicios en el rango.</td></tr>
                @endforelse
            </table>
        @endif

        @if ($clave === 'preoperacionales')
            <h2>Preoperacionales e inspecciones <span class="pill">{{ $desde->format('d/m/Y') }} – {{ $hasta->format('d/m/Y') }}</span></h2>
            <table class="lista">
                <tr><th style="width: 12%">Fecha</th><th>Tipo</th><th>Conductor</th><th>Extracto (FUEC)</th><th>Contrato</th><th style="width: 12%">Resultado</th></tr>
                @forelse ($datos['preoperacionales'] as $i)
                    <tr>
                        <td>{{ $i->fecha?->format('d/m/Y') }}</td>
                        <td>{{ $i->tipo?->getLabel() }}</td>
                        <td>{{ $i->conductor_nombre ?: '—' }}</td>
                        <td>{{ $i->numeroExtracto() ?: '—' }}</td>
                        <td>{{ $i->numeroContrato() ?: '—' }}</td>
                        <td class="{{ $i->apta() ? 'ok' : 'vencido' }}">{{ $i->resultado?->getLabel() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="vacio">Sin preoperacionales en el rango.</td></tr>
                @endforelse
            </table>
        @endif

        @if ($clave === 'mantenimientos')
            <h2>Mantenimientos <span class="pill">{{ $desde->format('d/m/Y') }} – {{ $hasta->format('d/m/Y') }}</span></h2>
            <table class="lista">
                <tr><th style="width: 12%">Fecha</th><th>Tipo</th><th>Rutinas / actividad</th><th>Taller</th><th style="width: 13%">Kilometraje</th><th style="width: 14%">Valor</th></tr>
                @forelse ($datos['mantenimientos'] as $m)
                    <tr>
                        <td>{{ $m->fecha?->format('d/m/Y') }}</td>
                        <td>{{ $m->tipo?->getLabel() }}</td>
                        <td>{{ $m->rutinas->pluck('nombre')->implode(', ') ?: $m->actividad }}</td>
                        <td>{{ $m->taller ?: '—' }}</td>
                        <td>{{ $m->kilometraje !== null ? number_format($m->kilometraje, 0, ',', '.') : '—' }}</td>
                        <td>{{ $m->valor !== null ? '$ '.number_format($m->valor, 0, ',', '.') : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="vacio">Sin mantenimientos en el rango.</td></tr>
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

        @if ($clave === 'planes')
            <h2>Planes de acción</h2>
            <table class="lista">
                <tr><th>Título</th><th style="width: 13%">Prioridad</th><th style="width: 15%">Límite</th><th style="width: 15%">Estado</th></tr>
                @forelse ($datos['planes'] as $p)
                    <tr>
                        <td>{{ $p->titulo }}</td>
                        <td>{{ $p->prioridad?->getLabel() ?? $p->prioridad }}</td>
                        <td>{{ $p->fecha_limite?->format('d/m/Y') ?: '—' }}</td>
                        <td>{{ $p->estado?->getLabel() ?? $p->estado }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="vacio">Sin planes de acción.</td></tr>
                @endforelse
            </table>
        @endif
    @endforeach
</main>

</body>
</html>
