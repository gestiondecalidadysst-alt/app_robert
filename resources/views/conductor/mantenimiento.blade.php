@extends('conductor.layout')
@section('titulo', 'Mantenimiento')
@section('cuerpo')
@include('conductor.partes.menu')

@if ($errors->any())<div class="error">{{ $errors->first() }}</div>@endif

<h1>Pendientes de mis vehículos</h1>
@forelse ($planes as $p)
    <article class="tarjeta" style="border-left: 5px solid {{ $p->prioridad->value === 'alta' ? '#b91c1c' : ($p->prioridad->value === 'media' ? '#b45309' : '#64748b') }}">
        <div class="fila">
            <span class="tenue" style="font-size:13px">{{ $p->numero }} · {{ $p->vehiculo?->placa }}</span>
            <span class="estado {{ $p->estado->value === 'por_verificar' ? 'info' : ($p->prioridad->value === 'alta' ? 'mal' : 'pend') }}">{{ $p->estado->value === 'por_verificar' ? 'Por verificar' : 'Prioridad '.mb_strtolower($p->prioridad->getLabel()) }}</span>
        </div>
        <div class="grande" style="font-size:16px">{{ $p->titulo }}</div>
        @foreach (collect($p->hallazgos ?? [])->take(4) as $h)
            <p class="dato">• {{ $h['descripcion'] }}{{ ($h['observacion'] ?? null) ? ': '.$h['observacion'] : '' }}</p>
        @endforeach
        @if ($p->estado->value === 'rechazado' && $p->observacion_cierre)
            <div class="error" style="margin-top:8px">La empresa no aceptó la evidencia: {{ $p->observacion_cierre }}</div>
        @endif
        @if ($p->prioridad->value === 'alta')
            <p class="nota">Mientras esté pendiente, el vehículo no queda apto en el preoperacional.</p>
        @endif
        @if ($p->estado->value !== 'por_verificar')
            <div class="botones"><a class="boton" href="{{ route('conductor.mantenimiento.registrar', ['vehiculo' => $p->vehiculo_id, 'plan' => $p->id]) }}">Registrar arreglo con evidencia</a></div>
        @else
            <p class="nota">Ya se registró el arreglo. La empresa lo está revisando.</p>
        @endif
    </article>
@empty
    <div class="tarjeta"><p class="tenue" style="margin:0">No hay planes de acción pendientes para sus vehículos.</p></div>
@endforelse

@if ($programadas->isNotEmpty())
    <h1 style="margin-top:22px">Próximos mantenimientos programados</h1>
    @foreach ($programadas as $a)
        @php $s = $a->situacion(); @endphp
        <div class="tarjeta" style="padding:12px 16px">
            <div class="fila">
                <strong>{{ $a->vehiculo?->placa }} · {{ $a->rutina_nombre }}</strong>
                <span class="estado {{ $s === 'vencida' ? 'mal' : ($s === 'proxima' ? 'pend' : 'info') }}">{{ $s === 'vencida' ? 'Vencido' : $a->fecha_programada->format('d/m') }}</span>
            </div>
            <p class="tenue" style="font-size:13px; margin:4px 0 0">Programado para el {{ $a->fecha_programada->format('d/m/Y') }}{{ $a->km_programado ? ' o a los '.number_format($a->km_programado, 0, ',', '.').' km' : '' }}. Cuando se haga, regístrelo con la evidencia.</p>
        </div>
    @endforeach
@endif

@if ($vehiculos->isNotEmpty())
    <h1 style="margin-top:22px">Registrar un mantenimiento</h1>
    <div class="tarjeta">
        <p class="tenue" style="margin-top:0">Si le hicieron un cambio de aceite, una reparación u otro trabajo al vehículo, regístrelo con la foto o la factura.</p>
        <div class="botones">
            @foreach ($vehiculos as $v)
                <a class="boton sec" href="{{ route('conductor.mantenimiento.registrar', $v) }}">{{ $v->placa }}{{ $v->numero_interno ? ' · '.$v->numero_interno : '' }}</a>
            @endforeach
        </div>
    </div>
@endif

@if ($recientes->isNotEmpty())
    <h1 style="margin-top:22px">Lo que he registrado</h1>
    @foreach ($recientes as $m)
        <div class="tarjeta" style="padding:12px 16px">
            <div class="fila">
                <strong>{{ $m->vehiculo?->placa }} · {{ $m->fecha->format('d/m/Y') }}</strong>
                <span class="estado {{ $m->tipo->value === 'preventivo' ? 'info' : 'pend' }}">{{ $m->tipo->getLabel() }}</span>
            </div>
            <p class="dato">{{ \Illuminate\Support\Str::limit($m->actividad, 120) }}</p>
            <p class="tenue" style="font-size:13px; margin:0">{{ $m->evidencias_count }} {{ $m->evidencias_count === 1 ? 'evidencia' : 'evidencias' }}{{ $m->plan ? ' · plan '.$m->plan->numero : '' }}</p>
        </div>
    @endforeach
@endif
@endsection
