<!doctype html>
<html lang="es">
<body style="margin:0;background:#F3F5F8;font-family:Arial,Helvetica,sans-serif;color:#111827">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:12px;overflow:hidden">
    <tr><td style="background:#1B2B3A;color:#ffffff;padding:18px 22px;font-size:16px;font-weight:bold">{{ $empresa }}</td></tr>
    <tr><td style="padding:22px;font-size:15px;line-height:1.55">
        <p style="margin:0 0 12px">Hola, {{ $destinatario }}:</p>
        <p style="margin:0 0 12px">{{ $detalle }}</p>
        <p style="margin:0 0 12px">Adjuntamos el documento en PDF.</p>
        @if ($enlace)
            <p style="margin:0 0 18px;text-align:center"><a href="{{ $enlace }}" style="display:inline-block;background:#C2410C;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:bold">Ver y verificar el recibo</a></p>
        @endif
        <p style="margin:0;color:#6b7280;font-size:13px">Gracias por su pago.</p>
    </td></tr>
    <tr><td style="padding:14px 22px;border-top:1px solid #e5e7eb;color:#6b7280;font-size:12px">Si usted no esperaba este mensaje, ignórelo.</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
