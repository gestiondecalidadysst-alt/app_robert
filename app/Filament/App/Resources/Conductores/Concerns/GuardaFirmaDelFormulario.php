<?php

namespace App\Filament\App\Resources\Conductores\Concerns;

use App\Actions\Pesv\GuardarFirmaConductor;
use App\Models\Conductor;
use App\Support\FirmaManuscrita;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Saca la firma (dibujada o subida) de los datos del formulario del conductor, la valida antes de
 * guardar y la registra después con GuardarFirmaConductor.
 */
trait GuardaFirmaDelFormulario
{
    protected string|UploadedFile|null $firmaPendiente = null;

    protected function separarFirma(array $data): array
    {
        $dibujada = $data['firma_dibujada'] ?? null;
        $archivo = $data['firma_archivo'] ?? null;
        if (is_array($archivo)) {
            $archivo = collect($archivo)->first();
        }
        unset($data['firma_dibujada'], $data['firma_archivo']);

        $this->firmaPendiente = null;

        if ($archivo instanceof UploadedFile) {
            $this->firmaPendiente = $archivo;
        } elseif (filled($dibujada)) {
            try {
                FirmaManuscrita::desdeDataUri($dibujada);
            } catch (ValidationException $e) {
                throw ValidationException::withMessages(['data.firma_dibujada' => $e->errors()['firma'][0] ?? 'La firma no es válida.']);
            }
            $this->firmaPendiente = $dibujada;
        }

        return $data;
    }

    protected function guardarFirmaPendiente(Conductor $conductor): void
    {
        if ($this->firmaPendiente === null) {
            return;
        }

        try {
            app(GuardarFirmaConductor::class)($conductor, $this->firmaPendiente, 'desde la hoja de vida');
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['data.firma_archivo' => $e->errors()['firma'][0] ?? 'La firma no es válida.']);
        } finally {
            $this->firmaPendiente = null;
        }
    }
}
