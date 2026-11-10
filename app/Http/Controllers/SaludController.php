<?php

namespace App\Http\Controllers;

use App\Support\Sistema\Salud;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Estado de salud de la aplicación en JSON, para monitores externos (UptimeRobot, n8n…).
 * Devuelve 200 si todo está sano y 503 si algún servicio falla, para que el monitor avise solo.
 */
class SaludController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $token = (string) config('ruta.salud_token');

        if ($token !== '') {
            $recibido = (string) ($request->header('X-Salud-Token') ?: $request->query('token', ''));

            abort_unless(hash_equals($token, $recibido), Response::HTTP_UNAUTHORIZED);
        }

        $chequeos = Salud::chequeos();
        $sano = Salud::sano($chequeos);

        return response()->json([
            'estado' => $sano ? 'ok' : 'falla',
            'fecha' => now()->toIso8601String(),
            'chequeos' => $chequeos,
        ], $sano ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
