<?php

namespace App\Http\Controllers\Conductor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Soporte PWA del portal del conductor: el service worker (para instalar la app y que arranque
 * rápido) y la página de respaldo cuando se abre sin conexión. Ambos son públicos.
 */
class PwaController extends Controller
{
    public function serviceWorker(Request $request): SymfonyResponse
    {
        // Red de seguridad: si alguien abre esta URL como página (navegación), lo mandamos al
        // portal en vez de mostrar el código. Una navegación del navegador pide `Accept: text/html`;
        // el registro del service worker pide `Accept: */*` y `Sec-Fetch-Dest: serviceworker`.
        // Miramos ambos porque algunos proxies (p. ej. Traefik/Easypanel) quitan `Sec-Fetch-Dest`.
        $esNavegacion = $request->header('Sec-Fetch-Dest') === 'document'
            || str_contains((string) $request->header('Accept'), 'text/html');

        if ($esNavegacion) {
            return redirect()->route('conductor.inicio');
        }

        $js = <<<'JS'
// Service worker del portal del conductor (Ruta): instalable + arranque rápido + respaldo offline + push.
const CACHE = 'conductor-v3';
const CACHE_PAGINAS = 'conductor-paginas-v1';
const PRECARGA = ['/conductor/offline', '/images/conductor-192.png', '/images/conductor-512.png', '/conductor.webmanifest'];

self.addEventListener('install', (e) => {
    e.waitUntil(caches.open(CACHE).then((c) => c.addAll(PRECARGA)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
    e.waitUntil(
        caches.keys()
            .then((claves) => Promise.all(claves.filter((k) => ![CACHE, CACHE_PAGINAS].includes(k)).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (e) => {
    const req = e.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    // Navegaciones del portal: la red primero; se guarda una copia para poder abrir la página
    // (p. ej. el preoperacional) sin señal. Sin conexión: la copia guardada o la página de respaldo.
    if (req.mode === 'navigate') {
        e.respondWith(
            fetch(req)
                .then((resp) => {
                    if (resp && resp.ok && resp.type === 'basic') {
                        const copia = resp.clone();
                        caches.open(CACHE_PAGINAS).then((c) => c.put(req, copia)).catch(() => {});
                    }
                    return resp;
                })
                .catch(() => caches.match(req).then((r) => r || caches.match('/conductor/offline')))
        );
        return;
    }

    // Íconos y manifest: desde la caché para que abra al instante.
    if (PRECARGA.includes(url.pathname)) {
        e.respondWith(caches.match(req).then((r) => r || fetch(req)));
    }
});

// Notificaciones push: muestra el aviso que envía el servidor.
self.addEventListener('push', (e) => {
    let d = {};
    try { d = e.data ? e.data.json() : {}; } catch (x) {}
    const titulo = d.title || 'Ruta';
    e.waitUntil(self.registration.showNotification(titulo, {
        body: d.body || '',
        icon: '/images/conductor-192.png',
        badge: '/images/conductor-192.png',
        tag: d.tag || undefined,
        data: { url: d.url || '/conductor' },
    }));
});

// Al tocar la notificación: abre o enfoca el portal.
self.addEventListener('notificationclick', (e) => {
    e.notification.close();
    const destino = (e.notification.data && e.notification.data.url) || '/conductor';
    e.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then((lista) => {
        for (const c of lista) {
            if (c.url.includes('/conductor') && 'focus' in c) return c.focus();
        }
        return clients.openWindow(destino);
    }));
});
JS;

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Service-Worker-Allowed' => '/conductor',
            'Cache-Control' => 'no-cache',
        ]);
    }

    public function offline(): Response
    {
        return response()->view('conductor.offline');
    }
}
