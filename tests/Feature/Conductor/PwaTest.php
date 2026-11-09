<?php

namespace Tests\Feature\Conductor;

use Tests\TestCase;

/**
 * PWA del portal del conductor (fase 1): el service worker y la página de respaldo responden sin
 * sesión, y el portal registra el service worker y enlaza el manifest.
 */
class PwaTest extends TestCase
{
    public function test_el_service_worker_es_publico_y_es_javascript(): void
    {
        // El registro del service worker pide Accept: */* (no es una navegación): recibe el JS.
        $respuesta = $this->get('/conductor/sw.js', ['Accept' => '*/*']);

        $respuesta->assertOk();
        $this->assertStringContainsString('javascript', strtolower($respuesta->headers->get('content-type') ?? ''));
        $respuesta->assertSee("addEventListener('push'", false);
        $respuesta->assertSee('conductor/offline', false);
    }

    public function test_abrir_el_service_worker_como_pagina_lleva_al_portal(): void
    {
        // Una navegación del usuario (Sec-Fetch-Dest: document) no debe mostrar el código.
        $this->get('/conductor/sw.js', ['Sec-Fetch-Dest' => 'document'])
            ->assertRedirect(route('conductor.inicio'));

        // Aunque el proxy (Traefik/Easypanel) quite Sec-Fetch-Dest, una navegación pide
        // Accept: text/html: también se redirige al portal en vez de mostrar el código.
        $this->get('/conductor/sw.js', ['Accept' => 'text/html,application/xhtml+xml'])
            ->assertRedirect(route('conductor.inicio'));
    }

    public function test_la_pagina_sin_conexion_responde(): void
    {
        $this->get('/conductor/offline')->assertOk()->assertSee('Sin conexión');
    }

    public function test_el_portal_registra_el_service_worker_y_enlaza_el_manifest(): void
    {
        $respuesta = $this->get('/conductor/ingresar');

        $respuesta->assertOk();
        $respuesta->assertSee('/conductor.webmanifest', false);
        $respuesta->assertSee('serviceWorker.register', false);
        $respuesta->assertSee('/conductor/sw.js', false);
    }
}
