<?php

namespace App\Actions\Pesv;

use App\Models\Conductor;
use App\Support\FirmaManuscrita;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Registra (o reemplaza) la firma del conductor: dibujada (PNG en data URI) o imagen escaneada.
 * Queda en disco privado. Cada preoperacional guarda su propia copia, así que reemplazarla no
 * altera los anteriores. El cambio queda en la auditoría.
 */
class GuardarFirmaConductor
{
    public const DISCO = 'local';

    public function __invoke(Conductor $conductor, string|UploadedFile $firma, ?string $registradaPor = null): Conductor
    {
        [$contenido, $extension] = $firma instanceof UploadedFile
            ? self::desdeArchivo($firma)
            : [FirmaManuscrita::desdeDataUri($firma), 'png'];

        $ruta = "conductores/{$conductor->empresa_id}/{$conductor->id}/firma-" . Str::lower(Str::random(10)) . ".{$extension}";
        Storage::disk(self::DISCO)->put($ruta, $contenido);

        $anterior = $conductor->firma_path;

        $conductor->forceFill(['firma_path' => $ruta, 'firma_actualizada_at' => now()])->save();

        if ($anterior && $anterior !== $ruta) {
            Storage::disk(self::DISCO)->delete($anterior);
        }

        activity('Conductor')->performedOn($conductor)->causedBy(auth()->user())
            ->tap(fn ($a) => $a->empresa_id = $conductor->empresa_id)
            ->log(($anterior ? 'Reemplazó' : 'Registró') . ' la firma del conductor' . ($registradaPor ? " ({$registradaPor})" : ''));

        return $conductor;
    }

    /** @return array{0: string, 1: string} */
    private static function desdeArchivo(UploadedFile $archivo): array
    {
        $info = @getimagesize($archivo->getRealPath());

        if (! $info || ! in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true) || $archivo->getSize() > 2 * 1024 * 1024) {
            throw ValidationException::withMessages(['firma' => 'La firma debe ser una imagen PNG o JPG de máximo 2 MB.']);
        }

        return [file_get_contents($archivo->getRealPath()), $info[2] === IMAGETYPE_PNG ? 'png' : 'jpg'];
    }
}
