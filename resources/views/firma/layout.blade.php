<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title>@yield('titulo', 'Firma de contrato')</title>
<style>
    :root { color-scheme: light; --azul: #1B2B3A; --naranja: #C2410C; --naranja-osc: #9A3412; --fondo: #F3F5F8; --borde: #e2e7ee; --tenue: #6b7280; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: var(--fondo); color: #111827; font-size: 16px; }
    .barra { background: var(--azul); color: #fff; padding: 14px 16px; }
    .barra small { display: block; color: #9fb0c2; font-size: 11px; text-transform: uppercase; letter-spacing: .08em; }
    .barra strong { font-size: 17px; }
    .contenedor { max-width: 760px; margin: 0 auto; padding: 16px; }
    .tarjeta { background: #fff; border-radius: 14px; box-shadow: 0 1px 3px rgba(0,0,0,.07); padding: 18px; margin-bottom: 16px; }
    .estado { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 999px; font-size: 13px; font-weight: 700; color: #fff; }
    .ok { background: #15803d; } .info { background: #1d4ed8; } .mal { background: #b91c1c; } .gris { background: #475569; }
    .boton { display: inline-flex; justify-content: center; align-items: center; gap: 8px; width: 100%; border: 0; border-radius: 10px; padding: 14px 18px; font-size: 16px; font-weight: 700; cursor: pointer; text-decoration: none; background: var(--naranja); color: #fff; }
    .boton:hover { background: var(--naranja-osc); }
    .boton.sec { background: #fff; color: var(--azul); border: 1px solid var(--borde); }
    .boton:disabled { opacity: .55; cursor: not-allowed; }
    .enlace { background: none; border: 0; color: var(--naranja); font-weight: 600; cursor: pointer; padding: 0; font-size: 14px; text-decoration: underline; }
    .error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; padding: 10px 12px; border-radius: 10px; font-size: 14px; margin-bottom: 12px; }
    .aviso { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; padding: 10px 12px; border-radius: 10px; font-size: 14px; margin-bottom: 12px; }
    .tenue { color: var(--tenue); font-size: 14px; }
    label { font-weight: 600; font-size: 14px; display: block; margin-bottom: 6px; }
    input[type=text] { width: 100%; font-size: 26px; letter-spacing: 10px; text-align: center; padding: 10px; border: 1px solid #cbd5e1; border-radius: 10px; font-family: ui-monospace, monospace; }
    .documento { font-size: 14px; line-height: 1.6; color: #1f2937; }
    .documento h2 { text-align: center; font-size: 16px; }
    .documento p { text-align: justify; margin: 0 0 10px; }
    .lienzo { width: 100%; height: 190px; border: 2px dashed #cbd5e1; border-radius: 12px; background: #fff; touch-action: none; display: block; }
    .fila { display: flex; gap: 10px; align-items: center; justify-content: space-between; }
    .check { display: flex; gap: 10px; align-items: flex-start; font-weight: 400; font-size: 14px; }
    .check input { width: 20px; height: 20px; margin-top: 2px; flex: none; }
    .pie { text-align: center; color: var(--tenue); font-size: 12px; padding: 8px 16px 24px; }
    @media (min-width: 640px) { .boton { width: auto; } }
</style>
</head>
<body>
@yield('cuerpo')
</body>
</html>
