<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use App\Models\Concerns\SeRegistraEnRndc;
use App\Services\Rndc\RegistrableRndc;
use App\Support\Carga\CatalogoRndc as C;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Manifiesto electrónico de carga (RNDC, proceso 4). Consecutivo único por empresa, lleva una o más
 * remesas; el flete pactado se compara con el SICE-TAC (obligatorio por el Decreto 1017/2025).
 */
class ManifiestoCarga extends Model implements RegistrableRndc
{
    use PerteneceAEmpresa, SeRegistraEnRndc;

    protected $table = 'manifiestos_carga';

    public const TIPOS = ['G' => 'General', 'P' => 'Paqueteo', 'Y' => 'Generales contenedores', 'V' => 'Generales consolidadores', 'C' => 'Contratos integrales'];

    public const RESPONSABLES_PAGO = ['E' => 'Empresa de transporte', 'D' => 'Destinatario', 'R' => 'Remitente', 'C' => 'Conductor'];

    protected $fillable = [
        'empresa_id', 'numero', 'tipo', 'fecha_expedicion', 'origen_id', 'destino_id', 'vehiculo_id', 'remolque_id',
        'conductor_id', 'titular_propietario_id', 'titular_tercero_id', 'valor_flete', 'valor_minimo_sicetac',
        'retencion_fuente_pct', 'reteica_por_mil', 'anticipo', 'municipio_pago_id', 'fecha_pago_saldo',
        'responsable_cargue', 'responsable_descargue', 'observaciones', 'codigo_verificacion', 'user_id', 'anulado_at', 'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_expedicion' => 'date', 'fecha_pago_saldo' => 'date', 'anulado_at' => 'datetime',
            'valor_flete' => 'integer', 'anticipo' => 'integer', 'valor_minimo_sicetac' => 'integer',
            'retencion_fuente_pct' => 'decimal:2', 'reteica_por_mil' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $m): void {
            $m->codigo_verificacion ??= Str::lower(Str::random(24));
            if (! $m->numero) {
                $m->numero = ((int) self::query()->where('empresa_id', $m->empresa_id)->max('numero')) + 1;
            }
        });
    }

    public function origen(): BelongsTo { return $this->belongsTo(Municipio::class, 'origen_id'); }
    public function destino(): BelongsTo { return $this->belongsTo(Municipio::class, 'destino_id'); }
    public function municipioPago(): BelongsTo { return $this->belongsTo(Municipio::class, 'municipio_pago_id'); }
    public function vehiculo(): BelongsTo { return $this->belongsTo(Vehiculo::class)->withoutGlobalScopes(); }
    public function remolque(): BelongsTo { return $this->belongsTo(Remolque::class); }
    public function conductor(): BelongsTo { return $this->belongsTo(Conductor::class)->withoutGlobalScopes(); }
    public function titularPropietario(): BelongsTo { return $this->belongsTo(Propietario::class, 'titular_propietario_id')->withoutGlobalScopes(); }
    public function titularTercero(): BelongsTo { return $this->belongsTo(TerceroCarga::class, 'titular_tercero_id'); }
    public function remesas(): HasMany { return $this->hasMany(RemesaCarga::class, 'manifiesto_id'); }
    public function cumplido(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(CumplidoManifiesto::class, 'manifiesto_id'); }

    /** El titular del manifiesto puede ser el propietario (persona natural o jurídica) o un tercero. */
    public function titular(): Propietario|TerceroCarga|null
    {
        return $this->titularPropietario ?? $this->titularTercero;
    }

    public function anulado(): bool { return $this->anulado_at !== null; }

    public function numeroFormateado(): string { return 'MC-' . str_pad((string) $this->numero, 6, '0', STR_PAD_LEFT); }

    public function saldo(): int
    {
        $retefuente = (int) round((float) $this->valor_flete * (float) $this->retencion_fuente_pct / 100);
        $reteica = (int) round((float) $this->valor_flete * (float) $this->reteica_por_mil / 1000);

        return max(0, (int) $this->valor_flete - (int) $this->anticipo - $retefuente - $reteica);
    }

    public function procesoRndc(): int { return 4; }

    public function etiquetaRndc(): string
    {
        return $this->numeroFormateado() . ' · ' . ($this->origen?->nombre ?? '—') . ' → ' . ($this->destino?->nombre ?? '—');
    }

    public function variablesRndc(Empresa $empresa): array
    {
        $this->loadMissing(['origen.departamento', 'destino.departamento', 'municipioPago', 'vehiculo', 'remolque', 'conductor.tipoIdentificacion',
            'titularPropietario.tipoIdentificacion', 'titularTercero.tipoIdentificacion', 'remesas']);
        $titular = $this->titular();
        $titularTipo = $titular instanceof Propietario ? $titular->tipoIdentificacion?->codigo
            : ($titular instanceof TerceroCarga ? $titular->tipoIdentificacion?->codigo : null);
        $titularDoc = $titular instanceof Propietario ? $titular->documento : ($titular?->documento ?? null);

        $vars = array_filter([
            'NUMNITEMPRESATRANSPORTE' => C::digitos($empresa->nit),
            'NUMMANIFIESTOCARGA' => str_pad((string) $this->numero, 4, '0', STR_PAD_LEFT),
            'CODOPERACIONTRANSPORTE' => $this->tipo,
            'FECHAEXPEDICIONMANIFIESTO' => $this->fecha_expedicion?->format('d/m/Y'),
            'CODMUNICIPIOORIGENMANIFIESTO' => C::municipio($this->origen?->codigo_dane),
            'CODMUNICIPIODESTMANIFIESTO' => C::municipio($this->destino?->codigo_dane),
            'CODIDTITULARMANIFIESTO' => C::tipoId($titularTipo),
            'NUMIDTITULARMANIFIESTO' => C::digitos((string) $titularDoc),
            'NUMPLACA' => Str::upper((string) $this->vehiculo?->placa),
            'NUMPLACAREMOLQUE' => $this->remolque ? Str::upper($this->remolque->placa) : null,
            'CODIDCONDUCTOR' => C::tipoId($this->conductor?->tipoIdentificacion?->codigo),
            'NUMIDCONDUCTOR' => C::digitos((string) $this->conductor?->documento),
            'VALORFLETEPACTADOVIAJE' => (int) $this->valor_flete,
            'RETENCIONFUENTEMANIFIESTOCARGA' => (float) $this->retencion_fuente_pct,
            'RETENCIONICAMANIFIESTOCARGA' => (float) $this->reteica_por_mil,
            'VALORANTICIPOMANIFIESTO' => (int) $this->anticipo,
            'CODMUNICIPIOPAGOSALDO' => C::municipio($this->municipioPago?->codigo_dane ?? $this->destino?->codigo_dane),
            'FECHAPAGOSALDOMANIFIESTO' => ($this->fecha_pago_saldo ?? $this->fecha_expedicion)?->format('d/m/Y'),
            'CODRESPONSABLEPAGOCARGUE' => $this->responsable_cargue,
            'CODRESPONSABLEPAGODESCARGUE' => $this->responsable_descargue,
            'OBSERVACIONES' => $this->observaciones ? C::texto($this->observaciones, 200) : null,
        ], fn ($v) => $v !== null && $v !== '');

        // Grupo REMESASMAN con los consecutivos de las remesas asociadas (procesoid=43).
        $vars['REMESASMAN'] = ['@procesoid' => 43, 'items' => $this->remesas->map(fn (RemesaCarga $r) => [
            'tag' => 'REMESA', 'vars' => ['CONSECUTIVOREMESA' => str_pad((string) $r->numero, 4, '0', STR_PAD_LEFT)],
        ])->all()];

        return $vars;
    }

    public function validarRndc(): array
    {
        $e = [];
        $this->loadMissing(['vehiculo', 'remolque', 'conductor', 'remesas', 'titularPropietario', 'titularTercero', 'origen', 'destino']);

        if (! $this->vehiculo || ! $this->vehiculo->esDeCarga()) {
            $e[] = 'El vehículo no tiene datos de carga (pestaña Carga en el vehículo).';
        }
        if ($this->vehiculo && ! in_array($this->vehiculo->estadoRndc(), ['registrado', 'desactualizado'], true)) {
            $e[] = 'Registre primero el vehículo en el RNDC.';
        }
        if ($this->remolque && ! in_array($this->remolque->estadoRndc(), ['registrado', 'desactualizado'], true)) {
            $e[] = 'Registre primero el remolque en el RNDC.';
        }
        if (! $this->conductor || ! in_array($this->conductor->estadoRndc(), ['registrado', 'desactualizado'], true)) {
            $e[] = 'Registre primero al conductor en el RNDC.';
        }
        $titular = $this->titular();
        if (! $titular) {
            $e[] = 'Falta el titular del manifiesto.';
        } elseif (! in_array($titular->estadoRndc(), ['registrado', 'desactualizado'], true)) {
            $e[] = 'Registre primero al titular del manifiesto en el RNDC.';
        }
        if ($this->remesas->isEmpty()) {
            $e[] = 'El manifiesto debe tener al menos una remesa asociada.';
        }
        foreach ($this->remesas as $r) {
            if (! in_array($r->estadoRndc(), ['registrado', 'desactualizado'], true)) {
                $e[] = "Registre primero en el RNDC la remesa {$r->numeroFormateado()}.";
            }
        }
        if ($this->valor_flete <= 0) {
            $e[] = 'El valor del flete pactado debe ser mayor que cero.';
        }
        if ($this->valor_minimo_sicetac && $this->valor_flete < (int) $this->valor_minimo_sicetac) {
            $e[] = 'El flete está por debajo del mínimo del SICE-TAC (Decreto 1017/2025).';
        }
        if ($this->anticipo > $this->valor_flete) {
            $e[] = 'El anticipo no puede ser mayor que el valor del flete.';
        }
        if ($this->anulado()) {
            $e[] = 'El manifiesto está anulado.';
        }

        return $e;
    }

    public function urlVerificacion(): string
    {
        return route('manifiesto.verificar', ['codigo' => $this->codigo_verificacion]);
    }

    public function urlPdf(): string
    {
        return route('manifiesto.pdf', ['codigo' => $this->codigo_verificacion]);
    }
}
