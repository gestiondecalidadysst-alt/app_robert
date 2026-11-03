<div style="display: grid; gap: .6rem; font-size: .875rem">
    <div style="color: rgb(100 116 139)">
        Línea base: {{ $a->fecha_original->format('d/m/Y') }}{{ $a->km_original ? ' · '.number_format($a->km_original, 0, ',', '.').' km' : '' }}
        @if ($a->mantenimiento)
            · <a href="{{ \App\Filament\App\Resources\Mantenimientos\MantenimientoResource::getUrl('view', ['record' => $a->mantenimiento]) }}" style="color: rgb(194 65 12); font-weight: 600">Ver mantenimiento y evidencias</a>
        @endif
    </div>
    @foreach (array_reverse($a->historial ?? []) as $h)
        <div style="border-left: 3px solid rgb(194 65 12 / .5); padding-left: .6rem">
            <div style="font-weight: 600">{{ ucfirst($h['tipo']) }} · {{ \Illuminate\Support\Carbon::parse($h['fecha'])->format('d/m/Y h:i a') }}{{ $h['usuario'] ? ' · '.$h['usuario'] : ' · sistema' }}</div>
            <div style="color: rgb(71 85 105)">{{ $h['detalle'] }}</div>
        </div>
    @endforeach
</div>
