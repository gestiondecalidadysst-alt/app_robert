{{--
    Páginas de error en español, sin depender de la base de datos ni de la sesión (pueden mostrarse
    cuando algo falla). Las de 500 y 503 muestran el código de referencia que queda en el log.
--}}
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>@yield('titulo') · Ruta</title>
<style>
    :root { --azul: #1B2B3A; --naranja: #C2410C; --tenue: #64748b; --borde: #e2e8f0; }
    * { box-sizing: border-box; }
    body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f1f5f9; font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; color: #0f172a; padding: 16px; }
    .caja { width: 100%; max-width: 480px; background: #fff; border: 1px solid var(--borde); border-radius: 18px; box-shadow: 0 10px 30px rgba(15, 23, 42, .08); overflow: hidden; }
    .barra { background: var(--azul); color: #fff; padding: 14px 20px; font-weight: 800; font-size: 20px; letter-spacing: -.02em; }
    .barra span { color: #fb923c; }
    .cuerpo { padding: 26px 22px 22px; }
    .codigo { display: inline-block; font-size: 13px; font-weight: 700; color: var(--naranja); background: #fff7ed; border: 1px solid #fed7aa; border-radius: 999px; padding: 3px 10px; }
    h1 { font-size: 22px; margin: 12px 0 8px; letter-spacing: -.01em; }
    p { margin: 0 0 12px; line-height: 1.55; color: #334155; }
    .ref { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; background: #f1f5f9; border: 1px dashed #cbd5e1; border-radius: 10px; padding: 10px 12px; font-size: 15px; font-weight: 700; text-align: center; letter-spacing: .04em; margin: 6px 0 14px; }
    .botones { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; }
    .boton { flex: 1 1 140px; display: inline-flex; justify-content: center; align-items: center; border-radius: 12px; padding: 12px 16px; font-weight: 700; font-size: 15px; text-decoration: none; background: var(--naranja); color: #fff; border: 0; cursor: pointer; }
    .boton.sec { background: #fff; color: var(--azul); border: 1.5px solid #cbd5e1; }
    .pie { text-align: center; color: var(--tenue); font-size: 12px; margin-top: 14px; }
</style>
</head>
<body>
<main>
    <div class="caja">
        <div class="barra">ruta<span>.</span></div>
        <div class="cuerpo">
            <span class="codigo">Error @yield('codigo')</span>
            <h1>@yield('titulo')</h1>
            @yield('mensaje')
            <div class="botones">
                <a class="boton sec" href="javascript:history.back()">Volver</a>
                <a class="boton" href="@yield('destino', url('/'))">@yield('destino_texto', 'Ir al inicio')</a>
            </div>
        </div>
    </div>
    <p class="pie">{{ now()->format('d/m/Y h:i a') }}</p>
</main>
</body>
</html>
