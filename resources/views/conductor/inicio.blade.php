@extends('conductor.layout')
@section('titulo', 'Mis servicios')
@section('cuerpo')
@include('conductor.partes.menu')

@if ($errors->any())<div class="error">{{ $errors->first() }}</div>@endif

@if ($sinFirma->isNotEmpty())
    <div class="tarjeta" style="border-left:5px solid #b45309">
        <h2>Falta su firma</h2>
        <p class="tenue" style="margin:0">Regístrela una sola vez para poder hacer los preoperacionales.</p>
        <div class="botones"><a class="boton" href="{{ route('conductor.firma') }}">Registrar mi firma</a></div>
    </div>
@endif

@php
    $planesAltos = ! \App\Support\Pesv\ServiciosConductor::conModulo(auth()->user(), \App\Enums\ModuloEmpresa::Mantenimiento) ? 0 : \App\Models\PlanAccion::query()->withoutGlobalScopes()->pendientes()->where('prioridad', 'alta')
        ->whereIn('vehiculo_id', $vehiculos->pluck('vehiculo.id')->merge($tarjetas->pluck('fuec.vehiculo_id'))->filter()->unique())->count();
@endphp
@if ($planesAltos)
    <div class="alerta">Hay {{ $planesAltos }} {{ $planesAltos === 1 ? 'plan de acción pendiente' : 'planes de acción pendientes' }} por fallas críticas. <a href="{{ route('conductor.mantenimiento') }}" style="color:inherit">Ver y registrar el arreglo</a>.</div>
@endif

<h1>Servicios asignados</h1>

@forelse ($tarjetas as $t)
    @php
        $f = $t['fuec'];
        $p = $t['preoperacional'];
        $vigente = $t['estado']->value === 'vigente';
        $varias = $conductores->pluck('empresa_id')->unique()->count() > 1;
    @endphp
    <article class="tarjeta {{ $vigente ? 'hoy' : '' }}">
        <div class="fila">
            <span class="tenue" style="font-size:13px">Servicio asignado{{ $varias ? ' · '.($f->empresa?->nombre_comercial ?: $f->empresa?->razon_social) : '' }}</span>
            <span class="estado {{ $vigente ? 'ok' : 'info' }}">{{ $vigente ? 'Hoy' : 'Programado' }}</span>
        </div>
        <div class="grande">{{ $f->origen?->nombre }} → {{ $f->destino?->nombre }}</div>
        <div class="numero">FUEC {{ $f->numero_formateado }}</div>
        <p class="dato" style="margin-top:8px"><b>{{ $f->fecha_inicio->format('d/m/Y') }}</b>{{ $f->fecha_fin->ne($f->fecha_inicio) ? ' al '.$f->fecha_fin->format('d/m/Y') : '' }} · {{ $f->horario()->franjaTexto() }} · Vehículo <b>{{ $f->placa }}</b>{{ $f->vehiculo?->numero_interno ? ' ('.$f->vehiculo->numero_interno.')' : '' }}</p>
        @if ($f->contrato?->cliente)<p class="dato">Cliente: {{ $f->contrato->cliente->nombre }}</p>@endif
        @if ($f->recorrido)<p class="dato tenue" style="font-size:13px">{{ \Illuminate\Support\Str::limit($f->recorrido, 140) }}</p>@endif

        @if ($vigente)
            <div class="linea-estado">
                @if (! $p)
                    <span class="estado pend">Pendiente</span> Preoperacional de hoy
                @elseif ($p->apta())
                    <span class="estado ok">✓ Apto</span> Preoperacional hecho a las {{ $p->created_at->format('h:i a') }}
                @else
                    <span class="estado mal">No apto</span> El vehículo no debe operar
                @endif
            </div>
        @endif

        <div class="botones">
            @if ($t['puede_preoperacional'])
                <a class="boton" href="{{ route('conductor.preoperacional', ['vehiculo' => $f->vehiculo_id, 'fuec' => $f->id]) }}">{{ $p ? 'Hacer de nuevo el preoperacional' : 'Hacer preoperacional' }}</a>
            @elseif ($p)
                <a class="boton sec" href="{{ route('conductor.inspeccion.pdf', $p) }}" target="_blank" rel="noopener">Ver preoperacional (PDF)</a>
            @endif

            @if ($t['servicio'])
                <a class="boton sec" href="{{ route('conductor.servicio', $t['servicio']) }}">{{ $t['servicio']->leido_at ? '✓ ' : '' }}Plan de viaje y rutograma{{ $t['servicio']->estado->value === 'en_curso' ? ' · en curso' : '' }}</a>
            @endif

            @if ($t['puede_extracto'])
                <a class="boton {{ $t['puede_preoperacional'] ? 'sec' : '' }}" href="{{ route('conductor.extracto', $f) }}" target="_blank" rel="noopener">Ver extracto (FUEC)</a>
            @else
                <span class="boton bloq" aria-disabled="true">🔒 Ver extracto (FUEC)</span>
                <p class="nota" style="margin-top:-4px">{{ $t['motivo_extracto'] }}</p>
            @endif
        </div>
    </article>
@empty
    <div class="tarjeta"><p class="tenue" style="margin:0">No tiene servicios asignados por ahora. Cuando la empresa expida un FUEC con usted como conductor, aparecerá aquí.</p></div>
@endforelse

@php $libres = $vehiculos->reject(fn ($a) => $tarjetas->contains(fn ($t) => $t['estado']->value === 'vigente' && $t['fuec']->vehiculo_id === $a['vehiculo']->id)); @endphp
@if ($libres->isNotEmpty())
    <h1 style="margin-top:22px">Mi vehículo</h1>
    @foreach ($libres as $a)
        @php $v = $a['vehiculo']; $p = $a['preoperacional']; @endphp
        <div class="tarjeta">
            <div class="fila">
                <div><div class="grande" style="margin:0">{{ $v->placa }}</div><span class="tenue">{{ $v->numero_interno ? 'No. interno '.$v->numero_interno : '' }}</span></div>
                @if (! $p)<span class="estado pend">Pendiente hoy</span>@elseif ($p->apta())<span class="estado ok">✓ Apto hoy</span>@else<span class="estado mal">No apto</span>@endif
            </div>
            <div class="botones">
                @if (! $p || ! $p->apta())
                    <a class="boton sec" href="{{ route('conductor.preoperacional', $v) }}">Hacer preoperacional</a>
                @else
                    <a class="boton sec" href="{{ route('conductor.inspeccion.pdf', $p) }}" target="_blank" rel="noopener">Ver preoperacional (PDF)</a>
                @endif
            </div>
        </div>
    @endforeach
@endif

<div class="pie"><a class="enlace" href="{{ route('conductor.clave') }}">Cambiar mi clave</a></div>
@endsection
