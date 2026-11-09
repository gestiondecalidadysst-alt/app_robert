<!doctype html>
<html lang="es">
<body style="margin:0;background:#F3F5F8;font-family:Arial,Helvetica,sans-serif;color:#111827">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden">
    <tr><td style="background:#15312a;color:#ffffff;padding:18px 22px;font-size:16px;font-weight:bold">{{ $empresa }}</td></tr>
    <tr><td style="padding:22px;font-size:15px;line-height:1.55">
        <p style="margin:0 0 12px">Estimado(a) {{ $destinatario }}:</p>
        <p style="margin:0 0 12px">Para el servicio amparado por el <strong>FUEC {{ $numeroFuec }}</strong> necesitamos el listado de pasajeros. Comparta el siguiente enlace con cada pasajero para que registre sus datos:</p>
        <p style="margin:0 0 18px;text-align:center">
            <a href="{{ $enlace }}" style="display:inline-block;background:#15312a;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:bold">Registrar pasajeros</a>
        </p>
        <p style="margin:0 0 12px;font-size:13px;color:#6b7280;word-break:break-all">Si el botón no funciona, copie este enlace:<br>{{ $enlace }}</p>
        @if ($cupos !== null)
            <p style="margin:0 0 12px;font-size:13px;color:#6b7280">Cupos disponibles: {{ $cupos }}.</p>
        @endif
        <p style="margin:16px 0 0;color:#6b7280;font-size:13px">{{ $empresa }}</p>
    </td></tr>
    <tr><td style="padding:14px 22px;border-top:1px solid #e5e7eb;color:#6b7280;font-size:12px">Cada pasajero completa su propio registro. Cuando estén todos, el operador cierra el listado.</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
