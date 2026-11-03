@php $p = fn ($v) => \App\Support\Gerencia\Indicadores::pesos($v); @endphp
<x-filament-widgets::widget>
    <div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr))">
        <x-filament::section heading="Principales clientes" :description="'Contratos nuevos · ' . $periodo">
            <table style="width: 100%; font-size: .85rem; border-collapse: collapse">
                <thead><tr style="color: rgb(100 116 139); text-align: left"><th style="padding: .3rem 0">Cliente</th><th style="text-align: right">Contratos</th><th style="text-align: right">Valor</th></tr></thead>
                <tbody>
                    @forelse ($clientes as $c)
                        <tr style="border-top: 1px solid rgba(148, 163, 184, .25)"><td style="padding: .35rem 0">{{ $c['cliente'] }}</td><td style="text-align: right">{{ $c['contratos'] }}</td><td style="text-align: right; font-weight: 600">{{ $p($c['valor']) }}</td></tr>
                    @empty
                        <tr><td colspan="3" style="padding: .5rem 0; color: rgb(100 116 139)">Sin contratos nuevos en el periodo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-filament::section>
        <x-filament::section heading="Costo de mantenimiento por vehículo" :description="$periodo">
            <table style="width: 100%; font-size: .85rem; border-collapse: collapse">
                <thead><tr style="color: rgb(100 116 139); text-align: left"><th style="padding: .3rem 0">Vehículo</th><th style="text-align: right">Mttos.</th><th style="text-align: right">Costo</th><th style="text-align: right">Km</th><th style="text-align: right">$/km</th></tr></thead>
                <tbody>
                    @forelse ($vehiculos as $v)
                        <tr style="border-top: 1px solid rgba(148, 163, 184, .25)">
                            <td style="padding: .35rem 0; font-weight: 600">{{ $v['placa'] }}</td><td style="text-align: right">{{ $v['mantenimientos'] }}</td>
                            <td style="text-align: right">{{ $p($v['costo']) }}</td><td style="text-align: right">{{ $v['km'] ? number_format($v['km'], 0, ',', '.') : '—' }}</td>
                            <td style="text-align: right">{{ $v['costo_km'] ? $p($v['costo_km']) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="padding: .5rem 0; color: rgb(100 116 139)">Sin mantenimientos en el periodo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-filament::section>
    </div>
</x-filament-widgets::widget>
