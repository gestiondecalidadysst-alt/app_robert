<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use App\Models\Concerns\SeRegistraEnRndc;
use App\Services\Rndc\AnulableRndc;
use App\Services\Rndc\RegistrableRndc;
use App\Support\Carga\AsociadorDeRemesas;
use App\Support\Carga\CatalogoRndc as C;
use App\Support\Carga\Sicetac;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * Manifiesto electrónico de carga (RNDC, proceso 4). Consecutivo único por empresa, lleva una o más
 * remesas; el flete pactado se compara con el SICE-TAC (obligatorio por el Decreto 1017/2025).
 */
class ManifiestoCarga extends Model implements AnulableRndc, RegistrableRndc
{
    use PerteneceAEmpresa, SeRegistraEnRndc;

    protected $table = 'manifiestos_carga';

    public const TIPOS = ['G' => 'General', 'P' => 'Paqueteo', 'Y' => 'Generales contenedores', 'V' => 'Generales consolidadores', 'C' => 'Contratos integrales'];

    public const RESPONSABLES_PAGO = ['E' => 'Empresa de transporte', 'D' => 'Destinatario', 'R' => 'Remitente', 'C' => 'Conductor'];

    protected $fillable = [
        'empresa_id', 'numero', 'tipo', 'fecha_expedicion', 'origen_id', 'destino_id', 'municipio_intermedio_id', 'trayectos_vacio', 'via_utilizar',
        'vehiculo_id', 'remolque_id', 'remolque_2_id', 'conductor_id', 'conductor_2_id', 'titular_propietario_id', 'titular_tercero_id',
        'valor_flete', 'valor_minimo_sicetac', 'retencion_fuente_pct', 'reteica_por_mil', 'retencion_fopat_pct',
        'anticipo', 'municipio_pago_id', 'fecha_pago_saldo', 'responsable_cargue', 'responsable_descargue',
        'observaciones', 'codigo_verificacion', 'user_id', 'anulado_at', 'motivo_anulacion',
        // No es una columna: lo recoge el mutador de abajo y se aplica al guardar.
        'remesas_ids',
    ];

    /**
     * Remesas elegidas en el formulario, pendientes de engancharse al guardar.
     *
     * @var array<int>|null
     */
    protected ?array $remesasElegidas = null;

    protected function casts(): array
    {
        return [
            'fecha_expedicion' => 'date', 'fecha_pago_saldo' => 'date', 'anulado_at' => 'datetime',
            'valor_flete' => 'integer', 'anticipo' => 'integer', 'valor_minimo_sicetac' => 'integer',
            'retencion_fuente_pct' => 'decimal:2', 'reteica_por_mil' => 'decimal:2', 'retencion_fopat_pct' => 'decimal:2',
            'trayectos_vacio' => 'array',
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

        // Las remesas se enganchan aquí, en el modelo, y no en una pantalla concreta:
        // el manifiesto se crea tanto desde la página del asistente como desde el modal
        // del listado, y antes solo una de las dos rutas hacía la asociación.
        static::saved(function (self $m): void {
            if ($m->remesasElegidas === null) {
                return;
            }

            $ids = $m->remesasElegidas;
            $m->remesasElegidas = null;

            $asociadas = AsociadorDeRemesas::aplicar($m, $ids);

            // Red de seguridad: vale más no expedir el manifiesto que expedirlo vacío
            // ante el RNDC. Se deshace todo y la pantalla explica por qué.
            if ($ids !== [] && $asociadas === 0) {
                Notification::make()->danger()
                    ->title('No se pudo asociar la remesa')
                    ->body('El manifiesto no se guardó. La remesa puede estar anulada o ir ya en otro manifiesto.')
                    ->persistent()->send();

                throw (new Halt)->rollBackDatabaseTransaction();
            }
        });
    }

    /** `remesas_ids` no es una columna: se guarda aparte y se aplica en el evento `saved`. */
    public function setRemesasIdsAttribute(mixed $valor): void
    {
        $this->remesasElegidas = array_values(array_filter(array_map('intval', (array) $valor)));
    }

    /** @return array<int> */
    public function getRemesasIdsAttribute(): array
    {
        return $this->remesasElegidas ?? ($this->exists ? $this->remesas()->pluck('id')->all() : []);
    }

    // ---- Anulación ante el RNDC (proceso 32) ------------------------------------------------

    /** Motivos que admite el RNDC para anular un manifiesto. */
    public const MOTIVOS_ANULACION = ['D' => 'Error en la digitación', 'S' => 'Cancelación del servicio'];

    /** Mínimo del SICE-TAC que aplica: el guardado o, si no hay, el de la tarifa de la ruta. */
    public function minimoSicetac(): ?int
    {
        return $this->valor_minimo_sicetac
            ? (int) $this->valor_minimo_sicetac
            : Sicetac::paraManifiesto($this);
    }

    public function procesoAnulacionRndc(): int
    {
        return 32;
    }

    public function variablesAnulacionRndc(Empresa $empresa, array $datos): array
    {
        return [
            'NUMNITEMPRESATRANSPORTE' => C::digitos($empresa->nit),
            'NUMMANIFIESTOCARGA' => str_pad((string) $this->numero, 4, '0', STR_PAD_LEFT),
            'MOTIVOANULACIONMANIFIESTO' => ($datos['motivo'] ?? 'D') === 'S' ? 'S' : 'D',
        ];
    }

    public function pendientesAnulacionRndc(array $datos): array
    {
        $errores = [];

        if ($this->estadoRndc() === 'pendiente') {
            $errores[] = 'Este manifiesto no está radicado en el RNDC: anúlelo solo aquí.';
        }

        // Manual: solo se anulan manifiestos no cumplidos y cuyas remesas tampoco lo estén.
        if ($this->cumplido()->exists()) {
            $errores[] = 'El manifiesto ya tiene cumplido: el RNDC no permite anularlo.';
        }

        $cumplidas = $this->remesas()->whereHas('cumplido')->count();

        if ($cumplidas > 0) {
            $errores[] = $cumplidas === 1
                ? 'Una de sus remesas ya está cumplida: el RNDC no permite anular el manifiesto.'
                : "{$cumplidas} de sus remesas ya están cumplidas: el RNDC no permite anular el manifiesto.";
        }

        return $errores;
    }

    public function origen(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'origen_id');
    }

    public function destino(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'destino_id');
    }

    public function municipioPago(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'municipio_pago_id');
    }

    public function municipioIntermedio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'municipio_intermedio_id');
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class)->withoutGlobalScopes();
    }

    public function remolque(): BelongsTo
    {
        return $this->belongsTo(Remolque::class);
    }

    public function remolque2(): BelongsTo
    {
        return $this->belongsTo(Remolque::class, 'remolque_2_id');
    }

    public function conductor(): BelongsTo
    {
        return $this->belongsTo(Conductor::class)->withoutGlobalScopes();
    }

    public function conductor2(): BelongsTo
    {
        return $this->belongsTo(Conductor::class, 'conductor_2_id')->withoutGlobalScopes();
    }

    public function titularPropietario(): BelongsTo
    {
        return $this->belongsTo(Propietario::class, 'titular_propietario_id')->withoutGlobalScopes();
    }

    public function titularTercero(): BelongsTo
    {
        return $this->belongsTo(TerceroCarga::class, 'titular_tercero_id');
    }

    public function remesas(): HasMany
    {
        return $this->hasMany(RemesaCarga::class, 'manifiesto_id');
    }

    public function cumplido(): HasOne
    {
        return $this->hasOne(CumplidoManifiesto::class, 'manifiesto_id');
    }

    /** El titular del manifiesto puede ser el propietario (persona natural o jurídica) o un tercero. */
    public function titular(): Propietario|TerceroCarga|null
    {
        return $this->titularPropietario ?? $this->titularTercero;
    }

    public function anulado(): bool
    {
        return $this->anulado_at !== null;
    }

    public function numeroFormateado(): string
    {
        return 'MC-'.str_pad((string) $this->numero, 6, '0', STR_PAD_LEFT);
    }

    public function saldo(): int
    {
        return max(0, (int) $this->valor_flete - (int) $this->anticipo - $this->retefuente() - $this->reteica() - $this->retefopat());
    }

    public function retefuente(): int
    {
        return (int) round((float) $this->valor_flete * (float) $this->retencion_fuente_pct / 100);
    }

    public function reteica(): int
    {
        return (int) round((float) $this->valor_flete * (float) $this->reteica_por_mil / 1000);
    }

    public function retefopat(): int
    {
        return (int) round((float) $this->valor_flete * (float) $this->retencion_fopat_pct / 1000);
    }

    public function valorNeto(): int
    {
        return max(0, (int) $this->valor_flete - $this->retefuente() - $this->reteica() - $this->retefopat());
    }

    public function procesoRndc(): int
    {
        return 4;
    }

    public function etiquetaRndc(): string
    {
        return $this->numeroFormateado().' · '.($this->origen?->nombre ?? '—').' → '.($this->destino?->nombre ?? '—');
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
        // El mínimo sale de la tarifa cargada aunque el campo esté vacío: dejarlo en
        // blanco no puede servir para saltarse el tope, que el RNDC rechaza igual.
        if (($minimo = $this->minimoSicetac()) && $this->valor_flete < $minimo) {
            $e[] = 'El flete está por debajo del mínimo del SICE-TAC ($ '.number_format($minimo, 0, ',', '.').', Decreto 1017/2025).';
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
