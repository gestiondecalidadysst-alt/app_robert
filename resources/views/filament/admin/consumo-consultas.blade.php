@php
    $p = fn ($v) => '$ ' . number_format((float) $v, 0, ',', '.');
    $tipos = \App\Enums\TipoConsultaTransito::cases();
@endphp
<x-filament-panels::page>
    <div style="display: flex; gap: .75rem; align-items: center; flex-wrap: wrap">
        <label style="font-weight: 600; font-size: .9rem">Mes</label>
        <select wire:model.live="mes" style="border: 1px solid rgb(203 213 225); border-radius: .5rem; padding: .4rem .7rem; background: transparent">
            @foreach ($meses as $valor => $nombre)<option value="{{ $valor }}">{{ $nombre }}</option>@endforeach
        </select>
    </div>

    <div style="display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr))">
        @foreach (['Consultas' => $totales['consultas'], 'Cobradas' => $totales['cobradas'], 'Valor a cobrar' => $p($totales['cobrar']), 'Costo proveedor' => $p($totales['costo']), 'Margen' => $p($totales['cobrar'] - $totales['costo'])] as $n => $v)
            <x-filament::section><div style="font-size: .75rem; color: rgb(100 116 139)">{{ $n }}</div><div style="font-size: 1.35rem; font-weight: 700">{{ $v }}</div></x-filament::section>
        @endforeach
    </div>

    <x-filament::section heading="Por empresa (para facturar)" description="Solo se cobran las consultas que el proveedor cobró.">
        <div style="overflow-x: auto">
            <table style="width: 100%; font-size: .85rem; border-collapse: collapse">
                <thead>
                    <tr style="text-align: left; color: rgb(100 116 139)">
                        <th style="padding: .35rem">Empresa</th>
                        @foreach ($tipos as $t)<th style="text-align: right">{{ $t->corto() }} {{ $t->esDeConductor() ? '(cond.)' : '(veh.)' }}</th>@endforeach
                        <th style="text-align: right">Cobradas</th><th style="text-align: right">A cobrar</th><th style="text-align: right">Costo</th><th style="text-align: right">Margen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($resumen as $r)
                        <tr style="border-top: 1px solid rgba(148, 163, 184, .25)">
                            <td style="padding: .4rem; font-weight: 600">{{ $r['empresa'] }}</td>
                            @foreach ($tipos as $t)<td style="text-align: right">{{ $r['por_tipo'][$t->value] }}</td>@endforeach
                            <td style="text-align: right">{{ $r['cobradas'] }}</td>
                            <td style="text-align: right; font-weight: 700">{{ $p($r['cobrar']) }}</td>
                            <td style="text-align: right">{{ $p($r['costo']) }}</td>
                            <td style="text-align: right; color: {{ $r['cobrar'] - $r['costo'] >= 0 ? 'rgb(21 128 61)' : 'rgb(185 28 28)' }}">{{ $p($r['cobrar'] - $r['costo']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" style="padding: .75rem; color: rgb(100 116 139)">Sin consultas en este mes.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    {{ $this->table }}
</x-filament-panels::page>
