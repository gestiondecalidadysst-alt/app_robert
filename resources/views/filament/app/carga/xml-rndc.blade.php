<div style="font-size: .85rem">
    @if ($pendientes)
        <div style="padding: .6rem .8rem; border: 1.5px solid rgb(185 28 28); border-radius: .5rem; color: rgb(185 28 28); margin-bottom: .75rem">
            <strong>El RNDC lo rechazaría:</strong>
            <ul style="margin: .3rem 0 0 1rem; list-style: disc">@foreach ($pendientes as $p)<li>{{ $p }}</li>@endforeach</ul>
        </div>
    @endif
    <pre style="white-space: pre-wrap; font-size: .75rem; padding: .75rem; border-radius: .5rem; background: rgba(148,163,184,.15); max-height: 26rem; overflow: auto">{{ $xml }}</pre>
</div>
