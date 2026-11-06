<?php

namespace App\Http\Controllers\Conductor;

use App\Http\Controllers\Controller;
use App\Models\SuscripcionPush;
use App\Support\Push\WebPush;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Suscripción del portal del conductor a las notificaciones Web Push, y envío de una de prueba.
 * El conductor debe estar con sesión abierta (va dentro del grupo con middleware 'conductor').
 */
class PushController extends Controller
{
    public function suscribir(Request $request): Response
    {
        $datos = $request->validate([
            'endpoint' => ['required', 'string', 'max:1024'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ]);

        $empresaId = $request->user()->conductores()->value('empresa_id');

        SuscripcionPush::registrar($request->user(), [
            'endpoint' => $datos['endpoint'],
            'p256dh' => $datos['keys']['p256dh'],
            'auth' => $datos['keys']['auth'],
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ], $empresaId);

        return response()->noContent();
    }

    public function baja(Request $request): Response
    {
        $endpoint = (string) $request->input('endpoint');
        if ($endpoint !== '') {
            $request->user()->suscripcionesPush()
                ->where('endpoint_hash', hash('sha256', $endpoint))->delete();
        }

        return response()->noContent();
    }

    public function probar(Request $request): JsonResponse
    {
        if (! WebPush::configurado()) {
            return response()->json(['ok' => false, 'motivo' => 'El servidor no tiene configuradas las notificaciones.'], 422);
        }

        $enviadas = WebPush::aUsuario(
            $request->user(),
            'Prueba de notificación',
            'Si ves este mensaje, los avisos quedaron activados en este dispositivo. 🎉',
            route('conductor.inicio'),
            'prueba',
        );

        return response()->json(['ok' => $enviadas > 0, 'enviadas' => $enviadas]);
    }
}
