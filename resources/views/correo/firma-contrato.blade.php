<!doctype html>
<html lang="es">
<body style="margin:0;background:#F3F5F8;font-family:Arial,Helvetica,sans-serif;color:#111827">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:12px;overflow:hidden">
    <tr><td style="background:#1B2B3A;color:#ffffff;padding:18px 22px;font-size:16px;font-weight:bold">{{ $empresa }}</td></tr>
    <tr><td style="padding:22px;font-size:15px;line-height:1.55">
        <p style="margin:0 0 12px">Hola, {{ $destinatario }}:</p>

        @if ($tipo === 'codigo')
            <p style="margin:0 0 12px">Su código para firmar el contrato No. <strong>{{ $numeroContrato }}</strong> es:</p>
            <p style="margin:0 0 12px;font-size:30px;letter-spacing:6px;font-weight:bold;text-align:center">{{ $codigo }}</p>
            <p style="margin:0 0 12px;color:#6b7280;font-size:13px">Vence en 10 minutos. No lo comparta con nadie: nadie de {{ $empresa }} se lo pedirá.</p>
        @elseif ($tipo === 'firmado')
            <p style="margin:0 0 12px">El contrato No. <strong>{{ $numeroContrato }}</strong> quedó firmado. Adjuntamos su copia en PDF con el certificado de firma electrónica.</p>
            @if ($enlace)
                <p style="margin:0 0 12px">También puede descargarla aquí:</p>
                <p style="margin:0 0 18px;text-align:center"><a href="{{ $enlace }}" style="display:inline-block;background:#C2410C;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:bold">Ver contrato firmado</a></p>
            @endif
        @else
            <p style="margin:0 0 12px">
                @if ($tipo === 'recordatorio') Le recordamos que el contrato @else {{ $empresa }} le envía el contrato @endif
                No. <strong>{{ $numeroContrato }}</strong> para su revisión y firma electrónica.
            </p>
            <p style="margin:0 0 18px;text-align:center"><a href="{{ $enlace }}" style="display:inline-block;background:#C2410C;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:bold">Revisar y firmar</a></p>
            <p style="margin:0 0 12px;color:#6b7280;font-size:13px">Para firmar le enviaremos un código de verificación a su celular o correo. El enlace vence el {{ $vence }}.</p>
        @endif
    </td></tr>
    <tr><td style="padding:14px 22px;border-top:1px solid #e5e7eb;color:#6b7280;font-size:12px">Si usted no esperaba este mensaje, ignórelo.</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
