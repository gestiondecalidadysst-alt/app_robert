<?php

namespace App\Http\Controllers;

use App\Models\Fuec;
use App\Models\PasajeroFuec;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autorregistro público de pasajeros al listado de un FUEC (Turismo / Grupo específico).
 * El enlace lo comparte el cliente con los pasajeros; cada uno se agrega a sí mismo.
 * No requiere sesión: el enlace es único y difícil de adivinar, y el operador cierra el listado.
 */
class RegistroPasajerosController extends Controller
{
    public function mostrar(Request $request, string $token): Response
    {
        $fuec = Fuec::porTokenPasajeros($token);

        if (! $fuec) {
            return response()->view('pasajeros.invalido', [], 404);
        }

        return response()->view('pasajeros.registro', $this->datosVista($fuec, $token));
    }

    public function registrar(Request $request, string $token): RedirectResponse
    {
        $fuec = Fuec::porTokenPasajeros($token) ?? abort(404);

        if ($fuec->anulado() || ! $fuec->listadoAbierto()) {
            return redirect()->route('pasajeros.registro', $token)->with('estado', 'cerrado');
        }

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:160'],
            'tipo_documento' => ['required', Rule::in(array_keys(PasajeroFuec::TIPOS_DOCUMENTO))],
            'documento' => ['required', 'string', 'max:30'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'es_menor' => ['sometimes', 'boolean'],
            'acudiente_nombre' => ['nullable', 'required_if:es_menor,1', 'string', 'max:160'],
            'acudiente_documento' => ['nullable', 'required_if:es_menor,1', 'string', 'max:30'],
        ], [
            'nombre.required' => 'Escribe el nombre completo.',
            'documento.required' => 'Escribe el número de documento.',
            'tipo_documento.in' => 'Elige un tipo de documento válido.',
            'acudiente_nombre.required_if' => 'Para un menor, indica el nombre del acudiente.',
            'acudiente_documento.required_if' => 'Para un menor, indica el documento del acudiente.',
        ]);

        $documento = preg_replace('/\s+/', '', $datos['documento']);
        $esMenor = (bool) ($datos['es_menor'] ?? false);

        // Cupo: respeta la capacidad del vehículo y un tope de seguridad aunque no haya capacidad declarada.
        if ($fuec->pasajeros()->count() >= $fuec->limitePasajeros()) {
            return redirect()->route('pasajeros.registro', $token)->with('estado', 'lleno');
        }

        // Documento ya registrado en este viaje.
        if ($fuec->pasajeros()->where('documento', $documento)->exists()) {
            return redirect()->route('pasajeros.registro', $token)->with('estado', 'duplicado');
        }

        try {
            $fuec->pasajeros()->create([
                'empresa_id' => $fuec->empresa_id,
                'orden' => (int) $fuec->pasajeros()->max('orden') + 1,
                'nombre' => trim($datos['nombre']),
                'tipo_documento' => $datos['tipo_documento'],
                'documento' => $documento,
                'telefono' => $datos['telefono'] ?? null,
                'es_menor' => $esMenor,
                'acudiente_nombre' => $esMenor ? ($datos['acudiente_nombre'] ?? null) : null,
                'acudiente_documento' => $esMenor ? ($datos['acudiente_documento'] ?? null) : null,
                'registrado_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Carrera: dos registros del mismo documento a la vez.
            return redirect()->route('pasajeros.registro', $token)->with('estado', 'duplicado');
        }

        return redirect()->route('pasajeros.registro', $token)->with('estado', 'ok');
    }

    /** @return array<string, mixed> */
    private function datosVista(Fuec $fuec, string $token): array
    {
        $d = $fuec->datos ?? [];

        return [
            'token' => $token,
            'abierto' => $fuec->listadoAbierto() && ! $fuec->anulado(),
            'cupos' => $fuec->cuposDisponibles(),
            'registrados' => $fuec->pasajeros()->count(),
            'empresa' => $d['empresa']['razon_social'] ?? '',
            'ruta' => trim(($d['origen'] ?? '').' – '.($d['destino'] ?? ''), ' –'),
            'fechaInicio' => $fuec->fecha_inicio?->format('d/m/Y'),
            'fechaFin' => $fuec->fecha_fin?->format('d/m/Y'),
            'tipos' => PasajeroFuec::TIPOS_DOCUMENTO,
        ];
    }
}
