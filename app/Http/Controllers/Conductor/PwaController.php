<?php

namespace App\Http\Controllers\Conductor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * Soporte PWA del portal del conductor: el service worker (para instalar la app y que arranque
 * rápido) y la página de respaldo cuando se abre sin conexión. Ambos son públicos.
 */
class PwaController extends Controller
{
    public function serviceWorker(): Response
    {
        $js = <<<'JS'
// Service worker del portal del conductor (Ruta). Fase 1: instalable + arranque rápido + respaldo offline.
const CACHE = 'conductor-v1';
const PRECARGA = ['/conductor/offline', '/images/conductor-192.png', '/images/conductor-512.png', '/conductor.webmanifest'];

self.addEventListener('install', (e) => {
    e.waitUntil(caches.open(CACHE).then((c) => c.addAll(PRECARGA)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
    e.waitUntil(
        caches.keys()
            .then((claves) => Promise.all(claves.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (e) => {
    const req = e.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    // Navegaciones del portal: la red primero; si no hay conexión, la página de respaldo.
    if (req.mode === 'navigate') {
        e.respondWith(fetch(req).catch(() => caches.match('/conductor/offline')));
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
