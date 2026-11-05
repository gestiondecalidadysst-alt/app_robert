<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use App\Models\Concerns\SeRegistraEnRndc;
use App\Services\Rndc\RegistrableRndc;
use App\Support\Carga\CatalogoRndc as C;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cumplido del manifiesto de carga (RNDC, proceso 6): tipo, fecha de entrega de documentos,
 * ajustes al pago (adicionales por cargue o descargue, descuentos, sobreanticipo) y motivos.
 */
class CumplidoManifiesto extends Model implements RegistrableRndc
{
    use PerteneceAEmpresa, SeRegistraEnRndc;

    protected $table = 'cumplidos_manifiesto';

    public const TIPOS = ['C' => 'Normal', 'S' => 'Suspensión'];

    public const MOTIVOS_SUSPENSION = ['A' => 'Accidente', 'V' => 'Varada', 'S' => 'Siniestro'];

    public const CONSECUENCIAS_SUSPENSION = [
        'C' => 'Cambio de conductor', 'V' => 'Cambio de cabezote o vehículo',
        'R' => 'Cambio de remolque o semirremolque', 'T' => 'Cambio total (vehículo y remolque)',
        'F' => 'Fin del viaje o pérdida total de la carga',
    ];

    public const MOTIVOS_ADICIONAL = ['C' => 'Variación en cantidad de carga (merma)', 'R' => 'Variación de la ruta', 'O' => 'Otro'];

    public const MOTIVOS_DESCUENTO = [
        'F' => 'Faltante', 'D' => 'Deterioro de la carga', 'C' => 'Variación cantidad (merma)', 'V' => 'Viaje incompleto por suspensión',
    ];

    protected $fillable = [
        'empresa_id', 'manifiesto_id', 'tipo', 'motivo_suspension', 'consecuencia_suspension',
        'fecha_entrega_documentos', 'valor_adicional_cargue', 'valor_adicional_descargue', 'valor_adicional_flete',
        'motivo_adicional', 'valor_descuento_flete', 'motivo_descuento', 'valor_sobreanticipo',
        'observaciones', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_entrega_documentos' => 'date',
            'valor_adicional_cargue' => 'integer', 'valor_adicional_descargue' => 'integer', 'valor_adicional_flete' => 'integer',
            'valor_descuento_flete' => 'integer', 'valor_sobreanticipo' => 'integer',
        ];
    }

    public function manifiesto(): BelongsTo
    {
        return $this->belongsTo(ManifiestoCarga::class, 'manifiesto_id');
    }

    public function procesoRndc(): int
    {
        return 6;
    }

    public function etiquetaRndc(): string
    {
        return 'Cumplido de ' . ($this->manifiesto?->numeroFormateado() ?? '—');
    }

    /** Lo que efectivamente queda por pagar al titular después del cumplido. */
    public function valorAPagar(): int
    {
        $m = $this->manifiesto;
        if (! $m) {
            return 0;
        }
        $flete = (int) $m->valor_flete + (int) $this->valor_adicional_cargue + (int) $this->valor_adicional_descargue
            + (int) $this->valor_adicional_flete - (int) $this->valor_descuento_flete;
        $retefuente = (int) round($flete * (float) $m->retencion_fuente_pct / 100);
        $reteica = (int) round($flete * (float) $m->reteica_por_mil / 1000);
        $retefopat = (int) round($flete * (float) ($m->retencion_fopat_pct ?? 0) / 1000);

        return max(0, $flete - (int) $m->anticipo - (int) $this->valor_sobreanticipo - $retefuente - $reteica - $retefopat);
    }

    public function variablesRndc(Empresa $empresa): array
    {
        $this->loadMissing('manifiesto');
        $m = $this->manifiesto;

        $vars = [
            'NUMNITEMPRESATRANSPORTE' => C::digitos($empresa->nit),
            'NUMMANIFIESTOCARGA' => str_pad((string) $m?->numero, 4, '0', STR_PAD_LEFT),
            'TIPOCUMPLIDOMANIFIESTO' => $this->tipo,
            'FECHAENTREGADOCUMENTOS' => $this->fecha_entrega_documentos?->format('d/m/Y'),
        ];
        if ($this->tipo === 'S') {
            $vars['MOTIVOSUSPENSIONMANIFIESTO'] = $this->motivo_suspension;
            $vars['CONSECUENCIASUSPENSION'] = $this->consecuencia_suspension;
        }
        foreach ([
            'VALORADICIONALHORASCARGUE' => $this->valor_adicional_cargue,
            'VALORADICIONALHORASDESCARGUE' => $this->valor_adicional_descargue,
            'VALORADICIONALFLETE' => $this->valor_adicional_flete,
            'VALORDESCUENTOFLETE' => $this->valor_descuento_flete,
            'VALORSOBREANTICIPO' => $this->valor_sobreanticipo,
        ] as $k => $v) {
            if ((int) $v > 0) {
                $vars[$k] = (int) $v;
            }
        }
        if (($this->valor_adicional_cargue + $this->valor_adicional_descargue + $this->valor_adicional_flete) > 0 && $this->motivo_adicional) {
            $vars['MOTIVOVALORADICIONAL'] = $this->motivo_adicional;
        }
        if ($this->valor_descuento_flete > 0 && $this->motivo_descuento) {
            $vars['MOTIVOVALORDESCUENTOMANIFIESTO'] = $this->motivo_descuento;
        }
        if ($this->observaciones) {
            $vars['OBSERVACIONES'] = C::texto($this->observaciones, 200);
        }

        return $vars;
    }

    public function validarRndc(): array
    {
        $e = [];
        $this->loadMissing('manifiesto.remesas');
        $m = $this->manifiesto;
        if (! $m) {
            return ['Falta el manifiesto.'];
        }
        if (! in_array($m->estadoRndc(), ['registrado', 'desactualizado'], true)) {
            $e[] = 'El manifiesto debe estar registrado en el RNDC antes del cumplido.';
        }
        // El RNDC exige cumplir primero todas las remesas del manifiesto.
        foreach ($m->remesas as $r) {
            $cum = CumplidoRemesa::query()->where('remesa_id', $r->id)->first();
            if (! $cum || ! in_array($cum->estadoRndc(), ['registrado', 'desactualizado'], true)) {
                $e[] = "Primero debe cumplir en el RNDC la remesa {$r->numeroFormateado()}.";
            }
        }
        if (! $this->fecha_entrega_documentos) {
            $e[] = 'Falta la fecha de entrega de documentos.';
        } elseif ($this->fecha_entrega_documentos->isFuture()) {
            $e[] = 'La fecha de entrega de documentos no puede ser futura.';
        }
        if ($this->tipo === 'S') {
            if (! $this->motivo_suspension) {
                $e[] = 'En suspensión debe reportarse el motivo.';
            }
            if (! $this->consecuencia_suspension) {
                $e[] = 'En suspensión debe reportarse la consecuencia (qué se cambió).';
            }
        }
        if ($this->valor_descuento_flete > 0 && ! $this->motivo_descuento) {
            $e[] = 'Si hay descuento al flete debe indicarse el motivo.';
        }
        if (($this->valor_adicional_cargue + $this->valor_adicional_descargue + $this->valor_adicional_flete) > 0 && ! $this->motivo_adicional) {
            $e[] = 'Si hay valores adicionales debe indicarse el motivo.';
        }
        if ($this->valor_sobreanticipo > 0 && $m->valor_flete > 0) {
            $neto = (int) $m->valor_flete - (int) $m->anticipo;
            if ($this->valor_sobreanticipo > $neto) {
                $e[] = 'El sobreanticipo no puede ser mayor al saldo neto por pagar.';
            }
        }

        return $e;
    }
}
