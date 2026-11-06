<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Cambie su contraseña</title>
    <style>
        :root { --azul:#1B2B3A; --naranja:#C2410C; --borde:#e5e7eb; --tenue:#6b7280; }
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
               background:#F3F5F8; font-family:Arial,Helvetica,sans-serif; color:#111827; padding:16px; }
        .tarjeta { background:#fff; border-radius:14px; box-shadow:0 6px 30px rgba(0,0,0,.08);
                   width:100%; max-width:420px; overflow:hidden; }
        .cabecera { background:var(--azul); color:#fff; padding:20px 24px; }
        .cabecera h1 { margin:0; font-size:18px; }
        .cabecera p { margin:6px 0 0; font-size:13px; opacity:.85; }
        .cuerpo { padding:24px; }
        label { display:block; font-size:14px; font-weight:bold; margin:0 0 6px; }
        input { width:100%; padding:11px 12px; border:1px solid var(--borde); border-radius:8px; font-size:15px; }
        .campo { margin:0 0 16px; }
        .ayuda { font-size:12px; color:var(--tenue); margin:6px 0 0; }
        .error { color:#b91c1c; font-size:13px; margin:6px 0 0; }
        .boton { width:100%; background:var(--naranja); color:#fff; border:0; border-radius:8px;
                 padding:12px; font-size:15px; font-weight:bold; cursor:pointer; }
    </style>
</head>
<body>
    <div class="tarjeta">
        <div class="cabecera">
            <h1>Cambie su contraseña</h1>
            <p>Entró con una contraseña temporal. Defina una propia para continuar.</p>
        </div>
        <div class="cuerpo">
            <form method="POST" action="{{ route('clave.obligatoria.guardar') }}">
                @csrf
                <div class="campo">
                    <label for="clave">Nueva contraseña</label>
                    <input type="password" name="clave" id="clave" autocomplete="new-password" autofocus required>
                    <p class="ayuda">Al menos 10 caracteres, con mayúsculas, minúsculas y números.</p>
                    @error('clave')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div class="campo">
                    <label for="clave_confirmation">Repita la contraseña</label>
                    <input type="password" name="clave_confirmation" id="clave_confirmation" autocomplete="new-password" required>
                </div>
                <button type="submit" class="boton">Guardar y continuar</button>
            </form>
        </div>
    </div>
</body>
</html>
