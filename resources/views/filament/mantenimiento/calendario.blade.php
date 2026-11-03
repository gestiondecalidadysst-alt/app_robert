@php
    $colores = ['programada' => '#1d4ed8', 'proxima' => '#b45309', 'vencida' => '#b91c1c', 'ejecutada' => '#15803d', 'cancelada' => '#64748b'];
    $etiquetas = \App\Filament\App\Resources\ProgramasMantenimiento\RelationManagers\ActividadesRelationManager::ETIQUETAS;
@endphp
<x-filament-panels::page>
    <div style="display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; justify-content: space-between">
        <div style="display: flex; gap: .5rem; align-items: center">
            <x-filament::button color="gray" size="sm" wire:click="cambiarMes(-1)" icon="heroicon-m-chevron-left">Anterior</x-filament::button>
            <div style="font-size: 1.15rem; font-weight: 700; min-width: 11rem; text-align: center">{{ ucfirst($inicio->translatedFormat('F Y')) }}</div>
            <x-filament::button color="gray" size="sm" wire:click="cambiarMes(1)" icon="heroicon-m-chevron-right" icon-position="after">Siguiente</x-filament::button>
            <x-filament::button color="gray" size="sm" wire:click="hoy">Hoy</x-filament::button>
        </div>
        <div style="display: flex; gap: .5rem; align-items: center">
            <select wire:model.live="vehiculo" style="border: 1px solid rgb(203 213 225); border-radius: .5rem; padding: .35rem .6rem; font-size: .875rem; background: transparent">
                <option value="">Todos los vehículos</option>
                @foreach ($vehiculos as $id => $placa)<option value="{{ $id }}">{{ $placa }}</option>@endforeach
            </select>
            <a href="{{ $urlPrograma }}" style="font-size: .875rem; font-weight: 600; color: rgb(194 65 12)">{{ $programa ? $programa->nombre.' ('.mb_strtolower($programa->estado->getLabel()).')' : 'Generar el programa del año' }} →</a>
        </div>
    </div>

    @unless ($programa)
        <div style="padding: 1rem; border-radius: .75rem; background: rgb(254 243 199); color: rgb(120 53 15); font-size: .875rem">
            No hay programa de mantenimiento para {{ $inicio->year }}. Genérelo en «Programa anual».
        </div>
    @endunless

    <div style="overflow-x: auto">
        <table style="width: 100%; min-width: 760px; border-collapse: collapse; table-layout: fixed">
            <thead>
                <tr>
                    @foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $d)
                        <th style="padding: .4rem; font-size: .75rem; color: rgb(100 116 139); text-align: left">{{ $d }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($semanas as $semana)
                    <tr>
                        @foreach ($semana as $dia)
                            @php $items = $porDia->get($dia->toDateString(), collect()); $fuera = $dia->month !== $inicio->month; @endphp
                            <td style="vertical-align: top; border: 1px solid rgb(148 163 184 / .25); height: 7rem; padding: .3rem; {{ $fuera ? 'opacity: .4;' : '' }} {{ $dia->isToday() ? 'background: rgb(255 237 213 / .45);' : '' }}">
                                <div style="font-size: .75rem; font-weight: 700; {{ $dia->isToday() ? 'color: rgb(194 65 12)' : '' }}">{{ $dia->day }}</div>
                                @foreach ($items->take(4) as $a)
                                    @php $s = $a->situacion(); @endphp
                                    <div title="{{ $a->vehiculo?->placa }} · {{ $a->rutina_nombre }} · {{ $etiquetas[$s] }}{{ $a->km_programado ? ' · '.number_format($a->km_programado, 0, ',', '.').' km' : '' }}"
                                         style="margin-top: .2rem; padding: .1rem .3rem; border-radius: .3rem; font-size: .68rem; line-height: 1.25; color: #fff; background: {{ $colores[$s] }}; overflow: hidden; text-overflow: ellipsis; white-space: nowrap">
                                        <b>{{ $a->vehiculo?->placa }}</b> {{ $a->rutina_nombre }}
                                    </div>
                                @endforeach
                                @if ($items->count() > 4)
                                    <div style="font-size: .68rem; color: rgb(100 116 139); margin-top: .2rem">+{{ $items->count() - 4 }} más</div>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div style="display: flex; flex-wrap: wrap; gap: 1rem; font-size: .75rem">
        @foreach ($etiquetas as $k => $t)
            <span style="display: inline-flex; align-items: center; gap: .3rem"><span style="width: .7rem; height: .7rem; border-radius: .2rem; background: {{ $colores[$k] }}"></span>{{ $t }}</span>
        @endforeach
        <span style="color: rgb(100 116 139)">Las ejecutadas se muestran el día en que se hicieron.</span>
    </div>
</x-filament-panels::page>
