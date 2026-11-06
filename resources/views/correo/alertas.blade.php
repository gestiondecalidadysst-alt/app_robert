<!doctype html>
<html lang="es">
<body style="margin:0;background:#F3F5F8;font-family:Arial,Helvetica,sans-serif;color:#111827">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden">
    <tr><td style="background:#1B2B3A;color:#ffffff;padding:18px 22px;font-size:16px;font-weight:bold">{{ $empresa }}</td></tr>
    <tr><td style="padding:22px;font-size:15px;line-height:1.55">
        <p style="margin:0 0 12px">Hola, {{ $destinatario }}:</p>
        <p style="margin:0 0 16px">{{ $intro }}</p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse">
            @foreach ($items as $item)
                <tr>
                    <td style="padding:10px 12px;border-left:4px solid {{ $item['urgente'] ? '#B91C1C' : '#C2410C' }};background:{{ $item['urgente'] ? '#FEF2F2' : '#FFF7ED' }};border-radius:6px;{{ ! $loop->last ? 'margin-bottom:8px' : '' }}">
                        <div style="font-weight:bold;font-size:14px">{{ $item['texto'] }}</div>
                        <div style="color:#6b7280;font-size:13px">{{ $item['detalle'] }}</div>
                    </td>
                </tr>
                @if (! $loop->last)<tr><td style="height:8px;line-height:8px">&nbsp;</td></tr>@endif
            @endforeach
        </table>
        <p style="margin:16px 0 0;color:#6b7280;font-size:13px">Por favor renueve o póngase al día a tiempo para evitar sanciones o bloqueos.</p>
    </td></tr>
    <tr><td style="padding:14px 22px;border-top:1px solid #e5e7eb;color:#6b7280;font-size:12px">Mensaje automático de {{ $empresa }}. Si cree que es un error, comuníquese con la empresa.</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
