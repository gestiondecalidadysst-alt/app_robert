<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use App\Models\Concerns\SeRegistraEnRndc;
use App\Services\Rndc\RegistrableRndc;
use App\Support\Carga\CatalogoRndc as C;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cumplido de una remesa terrestre de carga (RNDC, proceso 5). Lleva la cantidad entregada y los
 * tiempos reales de descargue, que el conductor puede reportar desde el portal.
 */
class CumplidoRemesa extends Model implements RegistrableRndc
{
    use PerteneceAEmpresa, SeRegistraEnRndc;

    protected $table = 'cumplidos_remesa';

    public const TIPOS = ['C' => 'Normal', 'S' => 'Suspensión'];

    public const MOTIVOS_SUSPENSION = ['A' => 'Accidente', 'V' => 'Varada', 'S' => 'Siniestro'];

    protected $fillable = [
        'empresa_id', 'remesa_id', 'tipo', 'motivo_suspension', 'cantidad_entregada',
        'llegada_descargue', 'entrada_descargue', 'salida_descargue', 'observaciones', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_entregada' => 'integer',
            'llegada_descargue' => 'datetime', 'entrada_descargue' => 'datetime', 'salida_descargue' => 'datetime',
        ];
    }

    public function remesa(): BelongsTo
    {
        return $this->belongsTo(RemesaCarga::class, 'remesa_id');
    }

    public function procesoRndc(): int
    {
        return 5;
    }

    public function etiquetaRndc(): string
    {
        return 'Cumplido de '.($this->remesa?->numeroFormateado() ?? '—');
    }

    public function variablesRndc(Empresa $empresa): array
    {
        $this->loadMissing('remesa.manifiesto');
        $r = $this->remesa;

        $vars = [
            'NUMNITEMPRESATRANSPORTE' => C::digitos($empresa->nit),
            'CONSECUTIVOREMESA' => str_pad((string) $r?->numero, 4, '0', STR_PAD_LEFT),
            'NUMMANIFIESTOCARGA' => $r?->manifiesto ? str_pad((string) $r->manifiesto->numero, 4, '0', STR_PAD_LEFT) : null,
            'TIPOCUMPLIDOREMESA' => $this->tipo,
            'MOTIVOSUSPENSIONREMESA' => $this->tipo === 'S' ? $this->motivo_suspension : null,
            'CANTIDADCARGADA' => $r?->operacion === 'V' ? null : (int) $r?->cantidad,
            'UNIDADMEDIDACAPACIDAD' => $r?->operacion === 'V' ? null : ($r?->unidad ?? 1),
        ];

        // Tiempos reales de descargue: obligatorios en Normal, no se reportan en Suspensión.
        if ($this->tipo !== 'S') {
            $vars = array_merge($vars, [
                'CANTIDADENTREGADA' => $r?->operacion === 'V' ? null : (int) $this->cantidad_entregada,
                'FECHALLEGADADESCARGUE' => $this->llegada_descargue?->format('d/m/Y'),
                'HORALLEGADADESCARGUECUMPLIDO' => $this->llegada_descargue?->format('H:i'),
                'FECHAENTRADADESCARGUE' => $this->entrada_descargue?->format('d/m/Y'),
                'HORAENTRADADESCARGUECUMPLIDO' => $this->entrada_descargue?->format('H:i'),
                'FECHASALIDADESCARGUE' => $this->salida_descargue?->format('d/m/Y'),
                'HORASALIDADESCARGUECUMPLIDO' => $this->salida_descargue?->format('H:i'),
            ]);
        }
        if ($this->observaciones) {
            $vars['OBSERVACIONES'] = C::texto($this->observaciones, 200);
        }

        return array_filter($vars, fn ($v) => $v !== null && $v !== '');
    }

    public function validarRndc(): array
    {
        $e = [];
        $this->loadMissing('remesa');
        if (! $this->remesa) {
            $e[] = 'Falta la remesa.';

            return $e;
        }
        if (! in_array($this->remesa->estadoRndc(), ['registrado', 'desactualizado'], true)) {
            $e[] = 'La remesa debe estar registrada en el RNDC antes del cumplido.';
        }
        if ($this->tipo === 'S' && ! $this->motivo_suspension) {
            $e[] = 'En suspensión debe reportarse el motivo (Accidente, Varada o Siniestro).';
        }
        if ($this->tipo !== 'S') {
            foreach (['llegada_descargue', 'entrada_descargue', 'salida_descargue'] as $campo) {
                if (! $this->{$campo}) {
                    $e[] = 'Falta '.str_replace('_', ' ', $campo).'.';
                }
            }
            if ($this->remesa->operacion !== 'V' && (! $this->cantidad_entregada || $this->cantidad_entregada <= 0)) {
                $e[] = 'Falta la cantidad entregada.';
            }
            if ($this->cantidad_entregada && $this->remesa->cantidad && $this->cantidad_entregada > $this->remesa->cantidad) {
                $e[] = 'La cantidad entregada no puede ser mayor que la cantidad cargada.';
            }
        }

        return $e;
    }
}
