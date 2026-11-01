@php
    $grupos = collect($getRecord()->respuestas)->groupBy('grupo');
    $texto = ['si' => 'Cumple', 'no' => 'No cumple', 'na' => 'N/A'];
    $color = ['si' => '#15803d', 'no' => '#b91c1c', 'na' => '#64748b'];
@endphp
<div style="display: grid; gap: 14px">
    @foreach ($grupos as $grupo => $items)
        <div>
            <div style="font-size: 12px; text-transform: uppercase; letter-spacing: .05em; color: rgb(100 116 139); font-weight: 600; margin-bottom: 4px">{{ $grupo }}</div>
            @foreach ($items as $r)
                <div style="display: flex; justify-content: space-between; gap: 12px; padding: 6px 0; border-top: 1px solid rgb(148 163 184 / .2); font-size: 14px">
                    <div>
                        {{ $r['descripcion'] }}
                        @if ($r['critico'])<span style="font-size: 10px; font-weight: 700; color: #b91c1c; margin-left: 4px">CRÍTICO</span>@endif
                        @if ($r['observacion'])<div style="font-size: 13px; color: #b91c1c">{{ $r['observacion'] }}</div>@endif
                    </div>
                    <span style="font-weight: 700; color: {{ $color[$r['respuesta']] }}; white-space: nowrap">{{ $texto[$r['respuesta']] }}</span>
                </div>
            @endforeach
        </div>
    @endforeach
</div>
