@php
    $hoy = today();
    $fondo = fn (string $hex) => $hex . '1a';
@endphp
<x-filament-panels::page>
    <style>
        .pl-barra { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; justify-content: space-between; }
        .pl-grupo { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
        .pl-ind { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: .6rem; }
        .pl-ind div { border: 1px solid rgba(148, 163, 184, .35); border-radius: .75rem; padding: .6rem .8rem; }
        .pl-ind small { display: block; font-size: .72rem; color: rgb(100 116 139); }
        .pl-ind strong { font-size: 1.2rem; }
        .pl-tabla { width: 100%; min-width: 980px; border-collapse: separate; border-spacing: 0; table-layout: fixed; }
        .pl-tabla th, .pl-tabla td { border-bottom: 1px solid rgba(148, 163, 184, .3); border-right: 1px solid rgba(148, 163, 184, .18); vertical-align: top; padding: .35rem; }
        .pl-tabla th { font-size: .75rem; color: rgb(100 116 139); text-align: left; font-weight: 600; background: rgba(148, 163, 184, .08); }
        .pl-tabla th.hoy { color: rgb(194 65 12); }
        .pl-tabla td.hoy { background: rgba(194, 65, 12, .04); }
        .pl-fila { position: sticky; left: 0; background: white; z-index: 1; }
        .dark .pl-fila { background: rgb(24 24 27); }
        .pl-chip { display: block; border-left: 3px solid; border-radius: .4rem; padding: .25rem .4rem; margin-bottom: .3rem; font-size: .72rem; line-height: 1.25; text-decoration: none; color: inherit; }
        .pl-chip:hover { filter: brightness(.96); }
        .pl-chip b { font-weight: 700; }
        .pl-mas { display: block; text-align: center; font-size: .9rem; color: rgb(148 163 184); text-decoration: none; border-radius: .4rem; }
        .pl-mas:hover { background: rgba(148, 163, 184, .15); color: rgb(194 65 12); }
        .pl-horas { font-size: .68rem; font-weight: 700; border-radius: 9999px; padding: 0 .4rem; display: inline-block; margin-bottom: .25rem; }
        .pl-aviso { padding: .75rem 1rem; border-radius: .75rem; background: rgb(254 243 199); color: rgb(120 53 15); font-size: .85rem; }
        .pl-aviso a { color: inherit; font-weight: 700; }
        .pl-ley { display: flex; flex-wrap: wrap; gap: .8rem; font-size: .75rem; color: rgb(100 116 139); }
        .pl-ley span::before { content: ''; display: inline-block; width: .6rem; height: .6rem; border-radius: 2px; margin-right: .3rem; background: var(--c); }
    </style>

    <div class="pl-barra">
        <div class="pl-grupo">
            <x-filament::button color="gray" size="sm" wire:click="cambiarSemana(-1)" icon="heroicon-m-chevron-left">Anterior</x-filament::button>
            <div style="font-size: 1.05rem; font-weight: 700; min-width: 15rem; text-align: center">
                {{ $dias->first()->translatedFormat('j M') }} – {{ $dias->last()->translatedFormat('j M Y') }}
            </div>
            <x-filament::button color="gray" size="sm" wire:click="cambiarSemana(1)" icon="heroicon-m-chevron-right" icon-position="after">Siguiente</x-filament::button>
            <x-filament::button color="gray" size="sm" wire:click="hoy">Esta semana</x-filament::button>
        </div>
        <div class="pl-grupo">
            <x-filament::tabs>
                <x-filament::tabs.item :active="$modo === 'vehiculo'" wire:click="$set('modo', 'vehiculo')" icon="heroicon-m-truck">Por vehículo</x-filament::tabs.item>
                <x-filament::tabs.item :active="$modo === 'conductor'" wire:click="$set('modo', 'conductor')" icon="heroicon-m-user">Por conductor</x-filament::tabs.item>
            </x-filament::tabs>
            <label style="display: flex; gap: .4rem; align-items: center; font-size: .85rem">
                <input type="checkbox" wire:model.live="soloOcupados"> Solo con servicios
            </label>
        </div>
    </div>

    <div class="pl-ind">
        @foreach ($indicadores as $nombre => $valor)
            <div><small>{{ $nombre }}</small><strong>{{ $valor }}</strong></div>
        @endforeach
    </div>

    @if ($proximos->isNotEmpty() || $avisos->isNotEmpty())
        <div class="pl-aviso">
            @if ($proximos->isNotEmpty())
                <div><strong>Salen en las próximas 48 h sin extracto:</strong>
                    @foreach ($proximos as $s)<a href="{{ $urlVer($s) }}">{{ $s->salida()->format('d/m H:i') }} {{ $s->ruta_texto }}</a>@if (! $loop->last) · @endif @endforeach
                </div>
            @endif
            @if ($avisos->isNotEmpty())
                <div style="margin-top: .3rem"><strong>Avisos de jornada o disponibilidad:</strong>
                    @foreach ($avisos as $id => $lista)
                        @php $s = $sinAsignar->firstWhere('id', $id) ?? $filas->flatMap(fn ($f) => $f['servicios'])->firstWhere('id', $id); @endphp
                        @if ($s)<a href="{{ $urlVer($s) }}" title="{{ implode(' ', $lista) }}">{{ $s->fecha_inicio->format('d/m') }} {{ $s->ruta_texto }}</a>@if (! $loop->last) · @endif @endif
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    <div class="pl-ley">
        @foreach (\App\Enums\EstadoServicio::cases() as $e)
            @continue($e === \App\Enums\EstadoServicio::Cancelado)
            <span style="--c: {{ $e->hex() }}">{{ $e->getLabel() }}</span>
        @endforeach
        <span style="--c: #b91c1c">⚠ Aviso de jornada</span>
    </div>

    <div style="overflow-x: auto; border: 1px solid rgba(148, 163, 184, .35); border-radius: .75rem">
        <table class="pl-tabla">
            <thead>
                <tr>
                    <th class="pl-fila" style="width: 170px">{{ $modo === 'conductor' ? 'Conductor' : 'Vehículo' }}</th>
                    @foreach ($dias as $d)
                        <th @class(['hoy' => $d->isSameDay($hoy)])>{{ ucfirst($d->translatedFormat('D j')) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @php $grupos = $sinAsignar->isNotEmpty() ? collect([['titulo' => $modo === 'conductor' ? 'Sin conductor' : 'Sin vehículo', 'detalle' => 'Por asignar', 'alerta' => false, 'clave' => 'sin', 'nuevo' => [], 'servicios' => $sinAsignar, 'minutos' => null]])->concat($filas) : $filas; @endphp
                @forelse ($grupos as $fila)
                    <tr wire:key="{{ $fila['clave'] }}">
                        <td class="pl-fila">
                            <div style="font-weight: 700; font-size: .85rem">{{ $fila['alerta'] ? '⚠ ' : '' }}{{ $fila['titulo'] }}</div>
                            <div style="font-size: .7rem; color: rgb(100 116 139)">{{ \Illuminate\Support\Str::limit($fila['detalle'], 34) }}</div>
                        </td>
                        @foreach ($dias as $d)
                            @php
                                $del = $fila['servicios']->filter(fn ($s) => $s->activoEl($d));
                                $min = $fila['minutos'][$d->toDateString()] ?? null;
                            @endphp
                            <td @class(['hoy' => $d->isSameDay($hoy)])>
                                @if ($min)
                                    <span class="pl-horas" style="background: {{ $min > $maxDia ? '#fee2e2' : '#e2e8f0' }}; color: {{ $min > $maxDia ? '#b91c1c' : '#334155' }}">{{ \App\Support\Operacion\Jornada::horas($min) }}</span>
                                @endif
                                @foreach ($del as $s)
                                    @php $hex = $s->estado->hex(); @endphp
                                    <a class="pl-chip" href="{{ $urlVer($s) }}" style="border-color: {{ $hex }}; background: {{ $fondo($hex) }}"
                                       title="{{ $s->ruta_texto }} · {{ $s->estado->getLabel() }}{{ isset($avisos[$s->id]) ? ' · ' . implode(' ', $avisos[$s->id]) : '' }}">
                                        <b>{{ $s->franja }}</b>@if (isset($avisos[$s->id])) <span style="color: #b91c1c">⚠</span>@endif<br>
                                        {{ \Illuminate\Support\Str::limit(($s->origen?->nombre ?? '') . ' → ' . ($s->destino?->nombre ?? ''), 30) }}<br>
                                        <span style="color: rgb(100 116 139)">
                                            {{ $modo === 'conductor' ? ($s->placa ?? 'Sin vehículo') : ($s->conductores->map(fn ($c) => \Illuminate\Support\Str::of($c->nombres)->before(' ') . ' ' . \Illuminate\Support\Str::of($c->apellidos)->before(' '))->implode(', ') ?: 'Sin conductor') }}
                                            @if ($s->fuec_id) · <span style="color: {{ $hex }}">FUEC</span>@endif
                                        </span>
                                    </a>
                                @endforeach
                                @if ($puedeCrear && ! $d->lt($hoy))
                                    <a class="pl-mas" href="{{ $urlNuevo([...$fila['nuevo'], 'fecha' => $d->toDateString()]) }}" title="Planificar servicio">+</a>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="8" style="padding: 1.5rem; text-align: center; color: rgb(100 116 139)">Sin {{ $modo === 'conductor' ? 'conductores' : 'vehículos' }} para mostrar.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($sinRuta->isNotEmpty())
        <p style="font-size: .8rem; color: rgb(100 116 139); margin: 0">
            {{ $sinRuta->count() }} servicio{{ $sinRuta->count() === 1 ? '' : 's' }} de esta semana sin rutograma. Cree las rutas frecuentes en «Rutogramas»: se asignan solas por origen y destino.
        </p>
    @endif
</x-filament-panels::page>
