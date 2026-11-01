{{-- Plan preventivo del vehículo (semáforo por rutina). Encabezado de la tabla de mantenimientos. Recibe $vehiculo. --}}
@php
    $plan = \App\Support\Mantenimiento\PlanPreventivo::para($vehiculo);
    $colores = ['vencido' => '#b91c1c', 'proximo' => '#b45309', 'al_dia' => '#15803d', 'sin_registro' => '#64748b'];
    $km = $plan->first()['km_actual'] ?? $vehiculo->kilometrajeActual();
@endphp
<div style="padding: 1rem 1.5rem">
    <div style="font-weight: 600; font-size: 1rem">Plan preventivo</div>
    <div style="font-size: .85rem; color: rgb(100 116 139); margin-bottom: .75rem">Kilometraje actual: {{ $km ? number_format($km, 0, ',', '.').' km' : 'sin reporte (llega con el preoperacional)' }}</div>
        @if ($plan->isEmpty())
            <p class="text-sm text-gray-500">No hay rutinas preventivas activas para este vehículo.</p>
        @else
            <div style="overflow-x: auto">
                <table style="width: 100%; font-size: .875rem; border-collapse: collapse">
                    <thead>
                        <tr style="text-align: left; color: rgb(100 116 139); font-size: .75rem">
                            <th style="padding: .4rem">Rutina</th><th style="padding: .4rem">Frecuencia</th><th style="padding: .4rem">Último</th>
                            <th style="padding: .4rem">Próximo</th><th style="padding: .4rem">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($plan as $f)
                            <tr style="border-top: 1px solid rgb(148 163 184 / .2)">
                                <td style="padding: .45rem .4rem; font-weight: 600">{{ $f['rutina']->nombre }}</td>
                                <td style="padding: .45rem .4rem; color: rgb(100 116 139)">{{ $f['rutina']->frecuencia }}</td>
                                <td style="padding: .45rem .4rem">
                                    @if ($f['ultimo']){{ $f['ultimo']->fecha->format('d/m/Y') }}{{ $f['ultimo']->kilometraje !== null ? ' · '.number_format($f['ultimo']->kilometraje, 0, ',', '.').' km' : '' }}@else — @endif
                                </td>
                                <td style="padding: .45rem .4rem">
                                    @if ($f['ultimo'])
                                        {{ collect([$f['proximo_km'] ? number_format($f['proximo_km'], 0, ',', '.').' km' : null, $f['proxima_fecha']?->format('d/m/Y')])->filter()->implode(' o ') }}
                                        <div style="font-size: .75rem; color: rgb(100 116 139)">{{ \App\Support\Mantenimiento\PlanPreventivo::textoFaltante($f) }}</div>
                                    @else
                                        <span style="font-size: .8rem; color: rgb(100 116 139)">Registre el último mantenimiento de esta rutina</span>
                                    @endif
                                </td>
                                <td style="padding: .45rem .4rem">
                                    <span style="display: inline-block; padding: .1rem .55rem; border-radius: 999px; font-size: .75rem; font-weight: 700; white-space: nowrap; color: #fff; background: {{ $colores[$f['estado']] }}">{{ \App\Support\Mantenimiento\PlanPreventivo::ETIQUETAS[$f['estado']] }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    <div style="font-weight: 600; font-size: 1rem; margin-top: 1.25rem">Historial de mantenimientos</div>
</div>
