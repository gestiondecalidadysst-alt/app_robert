@php
    $programa = $getRecord();
    $ind = \App\Actions\Mantenimiento\ProgramaAnual::indicadores($programa);
    $tot = ['programadas' => $ind->sum('programadas'), 'ejecutadas' => $ind->sum('ejecutadas'), 'canceladas' => $ind->sum('canceladas'), 'exigibles' => $ind->sum('exigibles')];
    $tot['cumplimiento'] = $tot['exigibles'] ? round($tot['ejecutadas'] * 100 / $tot['exigibles'], 1) : null;
    $vencidas = $programa->actividades()->pendientes()->whereDate('fecha_programada', '<', today())->count();
    $color = fn ($p) => $p === null ? 'rgb(100 116 139)' : ($p >= 90 ? '#15803d' : ($p >= 70 ? '#b45309' : '#b91c1c'));
@endphp
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: .75rem">
    @foreach ([...$ind->all(), 'Año' => $tot] as $t => $v)
        <div style="border: 1px solid rgb(148 163 184 / .3); border-radius: .75rem; padding: .75rem 1rem">
            <div style="font-size: .75rem; color: rgb(100 116 139); font-weight: 600">{{ $t === 'Año' ? 'Total del año' : 'Trimestre '.substr($t, 1) }}</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: {{ $color($v['cumplimiento']) }}">{{ $v['cumplimiento'] !== null ? $v['cumplimiento'].' %' : '—' }}</div>
            <div style="font-size: .8rem; color: rgb(100 116 139)">{{ $v['ejecutadas'] }} ejecutadas de {{ $v['programadas'] }} programadas{{ $v['canceladas'] ? ' · '.$v['canceladas'].' canceladas' : '' }}</div>
            <div style="font-size: .72rem; color: rgb(148 163 184)">{{ $v['cumplimiento'] === null ? 'Aún no vence ninguna' : 'Cumplimiento a la fecha' }}</div>
        </div>
    @endforeach
    <div style="border: 1px solid rgb(148 163 184 / .3); border-radius: .75rem; padding: .75rem 1rem">
        <div style="font-size: .75rem; color: rgb(100 116 139); font-weight: 600">Vencidas sin ejecutar</div>
        <div style="font-size: 1.5rem; font-weight: 700; color: {{ $vencidas ? '#b91c1c' : '#15803d' }}">{{ $vencidas }}</div>
        <div style="font-size: .8rem; color: rgb(100 116 139)">a hoy</div>
    </div>
</div>
