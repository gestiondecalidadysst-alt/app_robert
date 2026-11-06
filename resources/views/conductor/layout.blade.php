<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<meta name="theme-color" content="#1B2B3A">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Ruta Conductor">
<link rel="manifest" href="/conductor.webmanifest">
<link rel="icon" type="image/png" href="/images/conductor-192.png">
<link rel="apple-touch-icon" href="/images/conductor-192.png">
<title>@yield('titulo', 'Portal del conductor') · Ruta</title>
<style>
    :root { color-scheme: light; --azul: #1B2B3A; --naranja: #C2410C; --naranja-osc: #9A3412; --fondo: #F3F5F8; --borde: #e2e7ee; --tenue: #64748b; --verde: #15803d; --rojo: #b91c1c; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: var(--fondo); color: #0f172a; font-size: 16px; -webkit-text-size-adjust: 100%; }
    .barra { background: var(--azul); color: #fff; padding: 12px 16px; padding-top: max(12px, env(safe-area-inset-top)); display: flex; align-items: center; justify-content: space-between; gap: 12px; position: sticky; top: 0; z-index: 5; }
    .barra .marca { font-weight: 800; font-size: 19px; letter-spacing: -.02em; color: #fff; text-decoration: none; }
    .barra .marca span { color: #fb923c; }
    .barra small { display: block; color: #9fb0c2; font-size: 12px; }
    .barra form { margin: 0; }
    .barra button, .barra a.ir { background: transparent; color: #cbd5e1; border: 1px solid #334155; border-radius: 8px; padding: 6px 10px; font-size: 13px; cursor: pointer; text-decoration: none; }
    .contenedor { max-width: 640px; margin: 0 auto; padding: 16px; }
    h1 { font-size: 21px; margin: 4px 0 14px; }
    h2 { font-size: 16px; margin: 0 0 10px; }
    .tarjeta { background: #fff; border-radius: 14px; box-shadow: 0 1px 3px rgba(15,23,42,.08); padding: 16px; margin-bottom: 14px; border: 1px solid var(--borde); }
    .tarjeta.hoy { border-left: 5px solid var(--naranja); }
    .estado { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; color: #fff; white-space: nowrap; }
    .ok { background: var(--verde); } .info { background: #1d4ed8; } .mal { background: var(--rojo); } .gris { background: #475569; } .pend { background: #b45309; }
    .fila { display: flex; gap: 10px; align-items: center; justify-content: space-between; }
    .dato { font-size: 14px; color: #334155; margin: 3px 0; }
    .dato b { color: #0f172a; }
    .grande { font-size: 18px; font-weight: 700; margin: 8px 0 2px; }
    .numero { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13px; color: var(--tenue); letter-spacing: .02em; }
    .botones { display: grid; gap: 10px; margin-top: 14px; }
    .boton { display: flex; justify-content: center; align-items: center; gap: 8px; width: 100%; border: 0; border-radius: 12px; padding: 15px 16px; font-size: 16px; font-weight: 700; cursor: pointer; text-decoration: none; background: var(--naranja); color: #fff; min-height: 52px; }
    .boton:hover { background: var(--naranja-osc); }
    .boton.sec { background: #fff; color: var(--azul); border: 1.5px solid #cbd5e1; }
    .boton.sec:hover { background: #f8fafc; }
    .boton.bloq { background: #e2e8f0; color: #64748b; cursor: not-allowed; border: 0; }
    .linea-estado { display: flex; align-items: center; gap: 8px; font-size: 14px; margin-top: 12px; padding: 10px 12px; border-radius: 10px; background: #f8fafc; }
    .nota { font-size: 13px; color: var(--tenue); margin: 6px 2px 0; }
    .error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; padding: 11px 13px; border-radius: 10px; font-size: 14px; margin-bottom: 12px; }
    .aviso { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; padding: 11px 13px; border-radius: 10px; font-size: 14px; margin-bottom: 12px; }
    .alerta { background: #fef2f2; color: #7f1d1d; border: 2px solid #f87171; padding: 13px; border-radius: 10px; font-size: 15px; font-weight: 600; margin-bottom: 12px; }
    .tenue { color: var(--tenue); font-size: 14px; }
    label { font-weight: 600; font-size: 14px; display: block; margin-bottom: 6px; }
    input[type=text], input[type=password], input[type=number], input[type=tel], input[type=date], textarea { width: 100%; font-size: 17px; padding: 12px; border: 1px solid #cbd5e1; border-radius: 10px; font-family: inherit; background: #fff; }
    textarea { font-size: 15px; min-height: 76px; resize: vertical; }
    input:focus, textarea:focus { outline: 2px solid #fb923c; border-color: #fb923c; }
    .campo { margin-bottom: 14px; }
    .check { display: flex; gap: 10px; align-items: flex-start; font-weight: 400; font-size: 14px; }
    .check input { width: 22px; height: 22px; margin-top: 1px; flex: none; accent-color: var(--naranja); }
    .lienzo { width: 100%; height: 200px; border: 2px dashed #cbd5e1; border-radius: 12px; background: #fff; touch-action: none; display: block; }
    .enlace { background: none; border: 0; color: var(--naranja); font-weight: 600; cursor: pointer; padding: 0; font-size: 14px; text-decoration: underline; }
    .pie { text-align: center; color: var(--tenue); font-size: 12px; padding: 8px 16px 28px; }
    .menu { display: flex; gap: 8px; margin-bottom: 14px; }
    .menu a { flex: 1; text-align: center; padding: 9px; border-radius: 10px; background: #fff; border: 1px solid var(--borde); color: var(--azul); text-decoration: none; font-size: 14px; font-weight: 600; }
    .menu a.activo { background: var(--azul); color: #fff; border-color: var(--azul); }
    .instalar { display: none; align-items: center; gap: 10px; background: #fff7ed; border-bottom: 1px solid #fed7aa; color: #7c2d12; padding: 10px 16px; font-size: 14px; }
    .instalar .txt { flex: 1; }
    .instalar button { border: 0; border-radius: 8px; padding: 8px 12px; font-weight: 700; font-size: 13px; cursor: pointer; }
    .instalar .si { background: var(--naranja); color: #fff; }
    .instalar .no { background: transparent; color: #9a3412; text-decoration: underline; }
</style>
@stack('cabeza')
</head>
<body>
<header class="barra">
    <div>
        <a class="marca" href="{{ auth()->check() ? route('conductor.inicio') : route('conductor.ingresar') }}">ruta<span>.</span></a>
        <small>Portal del conductor</small>
    </div>
    @auth
        <div class="fila" style="gap:8px">
            <span style="font-size:13px; color:#e2e8f0; text-align:right; max-width:150px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap">{{ auth()->user()->name }}</span>
            <form method="post" action="{{ route('conductor.salir') }}">@csrf<button type="submit">Salir</button></form>
        </div>
    @endauth
</header>
<div class="instalar" id="barra-instalar">
    <span class="txt" id="instalar-texto">Instala esta app en tu celular para abrirla más rápido.</span>
    <button type="button" class="si" id="instalar-si">Instalar</button>
    <button type="button" class="no" id="instalar-no" aria-label="Ahora no">Ahora no</button>
</div>
<main class="contenedor">
    @if (session('aviso'))<div class="aviso">{{ session('aviso') }}</div>@endif
    @if (session('alerta'))<div class="alerta">{{ session('alerta') }}</div>@endif
    @yield('cuerpo')
</main>
@stack('scripts')
<script>
    // Registra el service worker: permite instalar la app y que abra rápido / con respaldo sin señal.
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('{{ route('conductor.sw') }}').catch(function () {});
        });
    }

    // Aviso de "Instalar app". En Android/Chrome usa el diálogo nativo; en iPhone muestra el instructivo.
    (function () {
        var barra = document.getElementById('barra-instalar');
        var texto = document.getElementById('instalar-texto');
        var si = document.getElementById('instalar-si');
        var no = document.getElementById('instalar-no');
        if (!barra) return;

        var oculto = false;
        try { oculto = localStorage.getItem('instalar-oculto') === '1'; } catch (e) {}
        var instalada = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
        if (oculto || instalada) return;

        function cerrar() {
            barra.style.display = 'none';
            try { localStorage.setItem('instalar-oculto', '1'); } catch (e) {}
        }
        no.addEventListener('click', cerrar);

        var prompt = null;
        window.addEventListener('beforeinstallprompt', function (e) {
            e.preventDefault();
            prompt = e;
            barra.style.display = 'flex';
        });

        si.addEventListener('click', function () {
            if (prompt) {
                prompt.prompt();
                prompt.userChoice.finally(cerrar);
            }
        });

        // iPhone/iPad (Safari) no dispara beforeinstallprompt: se instala desde Compartir.
        var esIOS = /iphone|ipad|ipod/i.test(window.navigator.userAgent) && !window.MSStream;
        if (esIOS) {
            texto.textContent = 'Para instalar: toca Compartir y luego “Agregar a inicio”.';
            si.style.display = 'none';
            barra.style.display = 'flex';
        }
    })();
</script>
</body>
</html>
