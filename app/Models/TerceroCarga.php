<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use App\Models\Concerns\SeRegistraEnRndc;
use App\Services\Rndc\RegistrableRndc;
use App\Support\Carga\CatalogoRndc as C;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tercero de carga para el RNDC: remitente, destinatario, titular de manifiesto o generador.
 * Una persona jurídica puede tener varias sedes: cada sede es un registro con su código.
 */
class TerceroCarga extends Model implements RegistrableRndc
{
    use PerteneceAEmpresa, SeRegistraEnRndc;

    protected $table = 'terceros_carga';

    protected $fillable = [
        'empresa_id', 'tipo_identificacion_id', 'documento', 'nombre', 'primer_apellido', 'segundo_apellido',
        'sede_codigo', 'sede_nombre', 'telefono', 'celular', 'email', 'direccion', 'municipio_id', 'roles', 'activo', 'observaciones',
    ];

    protected function casts(): array
    {
        return ['roles' => 'array', 'activo' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $t): void {
            $t->documento = C::digitos($t->documento);
            $t->telefono = C::digitos($t->telefono) ?: null;
            $t->celular = C::digitos($t->celular) ?: null;
            $t->sede_codigo = trim((string) $t->sede_codigo) ?: '1';
            $t->sede_nombre = mb_strtoupper(trim((string) $t->sede_nombre) ?: 'PRINCIPAL');
        });
    }

    public function tipoIdentificacion(): BelongsTo
    {
        return $this->belongsTo(TipoIdentificacion::class);
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }

    public function esJuridica(): bool
    {
        return C::tipoId($this->tipoIdentificacion?->codigo) === 'N';
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim($this->nombre . ' ' . $this->primer_apellido . ' ' . $this->segundo_apellido);
    }

    public function procesoRndc(): int
    {
        return 11;
    }

    public function etiquetaRndc(): string
    {
        return $this->nombre_completo . ' · ' . ($this->tipoIdentificacion?->codigo ?? '') . ' ' . $this->documento
            . ($this->esJuridica() ? ' · sede ' . $this->sede_codigo : '');
    }

    public function variablesRndc(Empresa $empresa): array
    {
        $this->loadMissing(['tipoIdentificacion', 'municipio']);
        $juridica = $this->esJuridica();

        return array_filter([
            'NUMNITEMPRESATRANSPORTE' => C::digitos($empresa->nit),
            'CODTIPOIDTERCERO' => C::tipoId($this->tipoIdentificacion?->codigo),
            'NUMIDTERCERO' => $this->documento,
            'NOMIDTERCERO' => C::texto($this->nombre, 100),
            'PRIMERAPELLIDOIDTERCERO' => $juridica ? null : C::texto($this->primer_apellido, 100),
            'SEGUNDOAPELLIDOIDTERCERO' => $juridica ? null : C::texto($this->segundo_apellido, 100),
            'CODSEDETERCERO' => $juridica ? $this->sede_codigo : null,
            'NOMSEDETERCERO' => $juridica ? C::texto($this->sede_nombre, 20) : null,
            'NUMTELEFONOCONTACTO' => $this->telefono,
            'NUMCELULARPERSONA' => $this->celular,
            'NOMENCLATURADIRECCION' => C::texto($this->direccion, 60),
            'CODMUNICIPIORNDC' => C::municipio($this->municipio?->codigo_dane),
        ], fn ($v) => $v !== null && $v !== '');
    }

    public function validarRndc(): array
    {
        return \App\Services\Rndc\ValidarTercero::errores(
            C::tipoId($this->tipoIdentificacion?->codigo), $this->documento, $this->nombre, $this->primer_apellido,
            $this->telefono, $this->celular, $this->direccion, $this->municipio_id, juridicaConSede: $this->esJuridica() ? $this->sede_codigo : null,
        );
    }
}
