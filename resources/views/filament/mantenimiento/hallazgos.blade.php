@php $hallazgos = $getRecord()->hallazgos ?? []; @endphp
<div style="display: grid; gap: .5rem">
    @forelse ($hallazgos as $h)
        <div style="display: flex; justify-content: space-between; gap: 1rem; padding: .5rem 0; border-top: 1px solid rgb(148 163 184 / .2); font-size: .875rem">
            <div>
                <strong>{{ $h['descripcion'] }}</strong>
                @if ($h['critico'] ?? false)<span style="font-size: .7rem; font-weight: 700; color: #b91c1c; margin-left: .25rem">CRÍTICO</span>@endif
                @if ($h['observacion'] ?? null)<div style="color: rgb(100 116 139)">{{ $h['observacion'] }}</div>@endif
            </div>
            <div style="text-align: right; white-space: nowrap; color: rgb(100 116 139); font-size: .8rem">
                {{ \Illuminate\Support\Carbon::parse($h['fecha'])->format('d/m/Y') }}
                @if (($h['veces'] ?? 1) > 1)<div style="color: #b91c1c; font-weight: 600">{{ $h['veces'] }} veces</div>@endif
            </div>
        </div>
    @empty
        <p style="font-size: .875rem; color: rgb(100 116 139)">Sin hallazgos.</p>
    @endforelse
</div>
