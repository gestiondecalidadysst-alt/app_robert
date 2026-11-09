<div style="text-align:center">
    @if (! $abierto)
        <p style="color:#b45309;font-weight:600;margin:0 0 12px">El listado está cerrado. Reábrelo para permitir nuevos registros.</p>
    @else
        <p style="color:#374151;margin:0 0 12px">Comparte este enlace con los pasajeros. Cada uno se registra por sí mismo.</p>
    @endif

    <img src="{{ $qr }}" alt="Código QR del enlace" style="width:180px;height:180px;margin:0 auto;display:block;border-radius:8px">

    <div style="margin-top:12px;padding:11px 12px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:8px;word-break:break-all;font-family:ui-monospace,monospace;font-size:13px;color:#111">{{ $url }}</div>

    @if ($cupos !== null)
        <p style="margin-top:10px;font-size:12px;color:#6b7280">Cupos disponibles: {{ $cupos }}</p>
    @endif
</div>
