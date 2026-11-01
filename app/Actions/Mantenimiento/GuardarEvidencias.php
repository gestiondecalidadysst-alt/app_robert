<?php

namespace App\Actions\Mantenimiento;

use App\Models\Evidencia;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Guarda archivos (PDF o imágenes, máx. 10 MB c/u) como evidencias de un mantenimiento o plan de acción,
 * en el disco privado. Comprueba que cada archivo quedó escrito.
 */
final class GuardarEvidencias
{
    /** @param  list<UploadedFile>  $archivos */
    public static function validar(array $archivos, string $campo = 'archivos'): void
    {
        foreach ($archivos as $archivo) {
            if (! $archivo instanceof UploadedFile || ! $archivo->isValid()) {
                throw ValidationException::withMessages([$campo => 'Un archivo no se pudo subir. Inténtelo de nuevo.']);
            }
            $mime = $archivo->getMimeType() ?: $archivo->getClientMimeType();
            if (! in_array($mime, Evidencia::TIPOS, true)) {
                throw ValidationException::withMessages([$campo => "«{$archivo->getClientOriginalName()}» no es PDF ni imagen (JPG, PNG, WEBP, HEIC)."]);
            }
            if ($archivo->getSize() > Evidencia::MAX_KB * 1024) {
                throw ValidationException::withMessages([$campo => "«{$archivo->getClientOriginalName()}» supera los 10 MB."]);
            }
        }
    }

    /**
     * @param  list<UploadedFile>  $archivos
     * @return list<Evidencia>
     */
    public static function en(Model $adjuntable, array $archivos, ?User $usuario = null, ?string $descripcion = null): array
    {
        self::validar($archivos);

        $carpeta = Str::plural(Str::snake(class_basename($adjuntable))) . "/{$adjuntable->empresa_id}/{$adjuntable->getKey()}";
        $guardadas = [];

        foreach ($archivos as $archivo) {
            $extension = Str::lower($archivo->getClientOriginalExtension() ?: $archivo->guessExtension() ?: 'bin');
            $ruta = $carpeta . '/' . now()->format('YmdHis') . '-' . Str::lower(Str::random(8)) . '.' . $extension;

            if (! Storage::disk(Evidencia::DISCO)->put($ruta, file_get_contents($archivo->getRealPath())) || ! Storage::disk(Evidencia::DISCO)->exists($ruta)) {
                report(new \RuntimeException("No se pudo escribir la evidencia en storage/app/private/{$ruta}"));

                throw ValidationException::withMessages(['archivos' => 'El servidor no pudo guardar los archivos. Avise al administrador (permisos o volumen de la carpeta storage).']);
            }

            $evidencia = new Evidencia([
                'path' => $ruta,
                'nombre' => Str::limit($archivo->getClientOriginalName(), 200, ''),
                'mime' => $archivo->getMimeType() ?: $archivo->getClientMimeType(),
                'tamano' => (int) $archivo->getSize(),
                'descripcion' => $descripcion,
                'user_id' => $usuario?->id,
            ]);
            $evidencia->empresa_id = $adjuntable->empresa_id;
            $evidencia->adjuntable()->associate($adjuntable);
            $evidencia->save();
            $guardadas[] = $evidencia;
        }

        return $guardadas;
    }
}
