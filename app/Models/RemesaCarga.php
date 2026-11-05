<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use App\Models\Concerns\SeRegistraEnRndc;
use App\Services\Rndc\AnulableRndc;
use App\Services\Rndc\RegistrableRndc;
use App\Support\Carga\CatalogoRndc as C;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * Remesa terrestre de carga (RNDC, proceso 3). Lleva el remitente, destinatario, mercancía y
 * horarios; se envía con todos sus datos (sin la información de carga previa, opcional).
 */
class RemesaCarga extends Model implements AnulableRndc, RegistrableRndc
{
    use PerteneceAEmpresa, SeRegistraEnRndc;

    protected $table = 'remesas_carga';

    public const OPERACIONES = ['G' => 'Carga general', 'P' => 'Paqueteo', 'C' => 'Contenedor cargado', 'V' => 'Contenedor vacío'];

    public const NATURALEZAS = [1 => 'Normal', 2 => 'Peligrosa', 3 => 'Extradimensionada', 4 => 'Extrapesada', 5 => 'Desechos peligrosos', 6 => 'Semovientes', 7 => 'Refrigerada'];

    public const DUENOS_POLIZA = ['E' => 'Empresa de transporte', 'G' => 'Generador', 'R' => 'Remitente', 'D' => 'Destinatario', 'N' => 'No existe'];

    protected $fillable = [
        'empresa_id', 'numero', 'fecha', 'operacion', 'empaque_codigo', 'naturaleza', 'producto_codigo',
        'producto_descripcion', 'cantidad', 'unidad', 'peso_contenedor_vacio', 'remitente_id', 'destinatario_id',
        'dueno_poliza', 'numero_poliza', 'vence_poliza', 'aseguradora_nit', 'horas_pacto_cargue', 'minutos_pacto_cargue',
        'horas_pacto_descargue', 'minutos_pacto_descargue', 'cita_cargue', 'cita_descargue', 'llegada_cargue',
        'entrada_cargue', 'salida_cargue', 'observaciones', 'manifiesto_id', 'codigo_verificacion', 'user_id', 'anulado_at', 'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date', 'vence_poliza' => 'date', 'anulado_at' => 'datetime',
            'cita_cargue' => 'datetime', 'cita_descargue' => 'datetime', 'llegada_cargue' => 'datetime',
            'entrada_cargue' => 'datetime', 'salida_cargue' => 'datetime',
            'cantidad' => 'integer', 'peso_contenedor_vacio' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $r): void {
            $r->codigo_verificacion ??= Str::lower(Str::random(24));
            if (! $r->numero) {
                $r->numero = ((int) self::query()->where('empresa_id', $r->empresa_id)->max('numero')) + 1;
            }
        });
    }

    public function remitente(): BelongsTo
    {
        return $this->belongsTo(TerceroCarga::class, 'remitente_id');
    }

    public function destinatario(): BelongsTo
    {
        return $this->belongsTo(TerceroCarga::class, 'destinatario_id');
    }

    public function manifiesto(): BelongsTo
    {
        return $this->belongsTo(ManifiestoCarga::class, 'manifiesto_id');
    }

    public function cumplido(): HasOne
    {
        return $this->hasOne(CumplidoRemesa::class, 'remesa_id');
    }

    /** Llegada, entrada y salida en cargue y descargue (tiempos logísticos). */
    public function tiemposLogisticos(): HasMany
    {
        return $this->hasMany(TiempoLogistico::class, 'remesa_id')->orderBy('ocurrido_at');
    }

    public function anulado(): bool
    {
        return $this->anulado_at !== null;
    }

    // ---- Anulación ante el RNDC (proceso 9) -------------------------------------------------

    /** Motivos que admite el RNDC para anular. */
    public const MOTIVOS_ANULACION = ['D' => 'Error en la digitación', 'S' => 'Cancelación del servicio'];

    /** Causas de transbordo cuando la remesa se libera de un manifiesto. */
    public const MOTIVOS_TRANSBORDO = ['A' => 'Accidente', 'V' => 'Varada', 'S' => 'Siniestro'];

    public function procesoAnulacionRndc(): int
    {
        return 9;
    }

    public function variablesAnulacionRndc(Empresa $empresa, array $datos): array
    {
        // [A] anula la remesa; [L] solo la libera del manifiesto, para un transbordo.
        $reversa = ($datos['reversa'] ?? 'A') === 'L' ? 'L' : 'A';

        $variables = [
            'NUMNITEMPRESATRANSPORTE' => C::digitos($empresa->nit),
            'CONSECUTIVOREMESA' => str_pad((string) $this->numero, 4, '0', STR_PAD_LEFT),
            'MOTIVOREVERSAREMESA' => $reversa,
            'MOTIVOANULACIONREMESA' => ($datos['motivo'] ?? 'D') === 'S' ? 'S' : 'D',
        ];

        if ($reversa === 'L') {
            $municipio = Municipio::find($datos['municipio_transbordo_id'] ?? null);
            $variables['CODMUNICIPIOTRANSBORDO'] = $municipio ? C::municipio($municipio->codigo_dane) : '';
            $variables['MOTIVOTRANSBORDOREMESA'] = in_array($datos['motivo_transbordo'] ?? null, ['A', 'V', 'S'], true)
                ? $datos['motivo_transbordo'] : 'V';
        }

        return array_filter($variables, fn ($v) => $v !== '' && $v !== null);
    }

    public function pendientesAnulacionRndc(array $datos): array
    {
        $errores = [];
        $reversa = $datos['reversa'] ?? 'A';

        if ($this->estadoRndc() === 'pendiente') {
            $errores[] = 'Esta remesa no está radicada en el RNDC: anúlela solo aquí.';
        }

        // El manual: solo se anulan remesas que no están en un manifiesto; las que sí
        // están se liberan (transbordo).
        if ($reversa === 'A' && $this->manifiesto_id) {
            $errores[] = 'La remesa va en un manifiesto: el RNDC solo permite liberarla (transbordo). Anule antes el manifiesto.';
        }

        if ($reversa === 'L' && ! $this->manifiesto_id) {
            $errores[] = 'Solo se libera una remesa que esté en un manifiesto.';
        }

        if ($reversa === 'L' && blank($datos['municipio_transbordo_id'] ?? null)) {
            $errores[] = 'Indique el municipio donde se hace el transbordo.';
        }

        if ($this->cumplido()->exists()) {
            $errores[] = 'La remesa ya tiene cumplido: el RNDC no permite anularla.';
        }

        return $errores;
    }

    public function numeroFormateado(): string
    {
        return 'RT-'.str_pad((string) $this->numero, 6, '0', STR_PAD_LEFT);
    }

    public function procesoRndc(): int
    {
        return 3;
    }

    public function etiquetaRndc(): string
    {
        return $this->numeroFormateado().' · '.($this->producto_descripcion ?? '').' · '.($this->cantidad ? number_format($this->cantidad, 0, ',', '.').' '.($this->unidad === 2 ? 'gal' : 'kg') : '');
    }

    public function variablesRndc(Empresa $empresa): array
    {
        $this->loadMissing(['remitente.tipoIdentificacion', 'remitente.municipio', 'destinatario.tipoIdentificacion', 'destinatario.municipio']);

        return array_filter([
            'NUMNITEMPRESATRANSPORTE' => C::digitos($empresa->nit),
            'CONSECUTIVOREMESA' => str_pad((string) $this->numero, 4, '0', STR_PAD_LEFT),
            'CODOPERACIONTRANSPORTE' => $this->operacion,
            'CODTIPOEMPAQUE' => $this->empaque_codigo,
            'CODNATURALEZACARGA' => $this->naturaleza,
            'DESCRIPCIONCORTAPRODUCTO' => C::texto($this->producto_descripcion, 60),
            'MERCANCIAREMESA' => $this->producto_codigo,
            'CANTIDADCARGADA' => $this->operacion === 'V' ? null : (int) $this->cantidad,
            'UNIDADMEDIDACAPACIDAD' => $this->operacion === 'V' ? null : $this->unidad,
            'PESOCONTENEDORVACIO' => in_array($this->operacion, ['C', 'V'], true) ? $this->peso_contenedor_vacio : null,
            'CODTIPOIDREMITENTE' => C::tipoId($this->remitente?->tipoIdentificacion?->codigo),
            'NUMIDREMITENTE' => C::digitos((string) $this->remitente?->documento),
            'CODSEDEREMITENTE' => $this->remitente?->esJuridica() ? $this->remitente->sede_codigo : null,
            'CODTIPOIDDESTINATARIO' => C::tipoId($this->destinatario?->tipoIdentificacion?->codigo),
            'NUMIDDESTINATARIO' => C::digitos((string) $this->destinatario?->documento),
            'CODSEDEDESTINATARIO' => $this->destinatario?->esJuridica() ? $this->destinatario->sede_codigo : null,
            'DUENOPOLIZA' => $this->dueno_poliza,
            'NUMPOLIZATRANSPORTE' => $this->dueno_poliza !== 'N' ? $this->numero_poliza : null,
            'FECHAVENCIMIENTOPOLIZACARGA' => $this->dueno_poliza !== 'N' ? $this->vence_poliza?->format('d/m/Y') : null,
            'COMPANIASEGURO' => $this->dueno_poliza !== 'N' ? C::digitos($this->aseguradora_nit) : null,
            'HORASPACTODESCARGUE' => $this->horas_pacto_descargue,
            'MINUTOSPACTODESCARGUE' => str_pad((string) $this->minutos_pacto_descargue, 2, '0', STR_PAD_LEFT),
            'FECHALLEGADACARGUE' => $this->llegada_cargue?->format('d/m/Y'),
            'HORALLEGADACARGUEREMESA' => $this->llegada_cargue?->format('H:i'),
            'FECHAENTRADACARGUE' => $this->entrada_cargue?->format('d/m/Y'),
            'HORAENTRADACARGUEREMESA' => $this->entrada_cargue?->format('H:i'),
            'FECHASALIDACARGUE' => $this->salida_cargue?->format('d/m/Y'),
            'HORASALIDACARGUEREMESA' => $this->salida_cargue?->format('H:i'),
            'FECHACITAPACTADACARGUE' => $this->cita_cargue?->format('d/m/Y'),
            'HORACITAPACTADACARGUE' => $this->cita_cargue?->format('H:i'),
            'FECHACITAPACTADADESCARGUE' => $this->cita_descargue?->format('d/m/Y'),
            'HORACITAPACTADADESCARGUEREMESA' => $this->cita_descargue?->format('H:i'),
            'OBSERVACIONES' => $this->observaciones ? C::texto($this->observaciones, 200) : null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    public function validarRndc(): array
    {
        $e = [];
        $this->loadMissing(['remitente', 'destinatario']);
        if (! $this->remitente || ! in_array($this->remitente->estadoRndc(), ['registrado', 'desactualizado'], true)) {
            $e[] = 'Registre primero al remitente en el RNDC.';
        }
        if (! $this->destinatario || ! in_array($this->destinatario->estadoRndc(), ['registrado', 'desactualizado'], true)) {
            $e[] = 'Registre primero al destinatario en el RNDC.';
        }
        if ($this->remitente && $this->destinatario && $this->remitente->municipio_id === $this->destinatario->municipio_id
            && $this->naturaleza !== 5 && $this->operacion !== 'V') {
            $e[] = 'Remitente y destinatario no pueden estar en el mismo municipio (salvo desechos peligrosos o contenedor vacío).';
        }
        if ($this->operacion !== 'V' && (! $this->cantidad || $this->cantidad <= 0)) {
            $e[] = 'Falta la cantidad cargada.';
        }
        if ($this->dueno_poliza !== 'N' && (blank($this->numero_poliza) || ! $this->vence_poliza || blank($this->aseguradora_nit))) {
            $e[] = 'Si hay póliza, debe tener número, vencimiento y NIT de la aseguradora.';
        }
        if ($this->anulado()) {
            $e[] = 'La remesa está anulada.';
        }

        return $e;
    }
}
