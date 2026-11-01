@php
    $plan = $getRecord();
    $mantenimientos = $plan->mantenimientos()->with('evidencias')->latest('fecha')->get();
@endphp
<div style="display: grid; gap: 1.25rem">
    @foreach ($mantenimientos as $m)
        <div>
            <a href="{{ \App\Filament\App\Resources\Mantenimientos\MantenimientoResource::getUrl('view', ['record' => $m]) }}" style="font-weight: 600; color: rgb(194 65 12)">
                Mantenimiento del {{ $m->fecha->format('d/m/Y') }} · {{ $m->tipo->getLabel() }} · {{ \Illuminate\Support\Str::limit($m->actividad, 80) }}
            </a>
            <div style="margin-top: .5rem">@include('filament.mantenimiento.evidencias', ['evidencias' => $m->evidencias])</div>
        </div>
    @endforeach
    @if ($plan->evidencias->isNotEmpty() || $mantenimientos->isEmpty())
        <div>
            @if ($mantenimientos->isNotEmpty())<div style="font-weight: 600; margin-bottom: .5rem">Evidencias adjuntas al plan</div>@endif
            @include('filament.mantenimiento.evidencias', ['evidencias' => $plan->evidencias])
        </div>
    @endif
</div>
