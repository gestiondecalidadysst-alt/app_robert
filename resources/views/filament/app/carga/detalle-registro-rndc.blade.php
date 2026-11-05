@php $etiquetas = \App\Support\Pdf\PdfFichaRndc::VARIABLES; @endphp
<div style="font-size: .85rem; display: grid; gap: .75rem">
    <div><strong>{{ $r->etiquetaEstado() }}</strong>{{ $r->ingresoid ? ' · radicado No. ' . $r->ingresoid : '' }} · {{ $r->created_at->timezone(config('app.timezone'))->format('d/m/Y h:i a') }}{{ $r->duracion_ms ? ' · ' . $r->duracion_ms . ' ms' : '' }}</div>
    @if ($r->errores)
        <div style="padding: .6rem .8rem; border: 1.5px solid rgb(185 28 28); border-radius: .5rem; color: rgb(185 28 28)">
            <ul style="margin: 0 0 0 1rem; list-style: disc">@foreach ($r->errores as $e)<li>{{ $e['codigo'] ? $e['codigo'] . ': ' : '' }}{{ $e['mensaje'] }}</li>@endforeach</ul>
        </div>
    @endif
    <table style="width: 100%; border-collapse: collapse; font-size: .8rem">
        @foreach ($r->variables ?? [] as $k => $v)
            <tr style="border-top: 1px solid rgba(148,163,184,.3)"><td style="padding: .25rem .4rem; color: rgb(100 116 139)">{{ $etiquetas[$k] ?? $k }}</td>{{-- Los grupos anidados (REMESASMAN) son arrays: e() revienta con ellos. --}}
<td style="padding: .25rem .4rem; font-weight: 600">{{ is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $v }}</td></tr>
        @endforeach
    </table>
</div>
