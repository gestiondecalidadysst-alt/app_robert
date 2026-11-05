<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use App\Models\Concerns\SeRegistraEnRndc;
use App\Services\Rndc\RegistrableRndc;
use App\Services\Rndc\ValidarVehiculo;
use App\Support\Carga\CatalogoRndc as C;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** Semirremolque, remolque o remolque balanceado (placa R + 5 números). Se registra en el RNDC como vehículo. */
class Remolque extends Model implements RegistrableRndc
{
    use PerteneceAEmpresa, SeRegistraEnRndc;

    protected $fillable = [
        'empresa_id', 'placa', 'configuracion', 'numero_ejes', 'marca_codigo', 'marca_nombre', 'modelo', 'peso_vacio',
        'capacidad', 'unidad_capacidad', 'carroceria_codigo', 'propietario_id', 'tenedor_id', 'activo', 'observaciones',
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'configuracion' => 'integer', 'modelo' => 'integer', 'peso_vacio' => 'integer', 'capacidad' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(fn (self $r) => $r->placa = Str::upper(preg_replace('/[^A-Za-z0-9]/', '', (string) $r->placa)));
    }

    public function propietario(): BelongsTo
    {
        return $this->belongsTo(Propietario::class)->withoutGlobalScopes();
    }

    public function tenedor(): BelongsTo
    {
        return $this->belongsTo(Propietario::class, 'tenedor_id')->withoutGlobalScopes();
    }

    public function procesoRndc(): int
    {
        return 12;
    }

    public function etiquetaRndc(): string
    {
        return 'Remolque '.$this->placa.' · '.(C::CONFIGURACIONES_REMOLQUE[$this->configuracion] ?? $this->configuracion);
    }

    public function variablesRndc(Empresa $empresa): array
    {
        $this->loadMissing(['propietario.tipoIdentificacion', 'tenedor.tipoIdentificacion']);
        $tenedor = $this->tenedor ?? $this->propietario;
        $ejes = in_array($this->configuracion, C::CON_EJES, true);
        $capacidad = in_array($this->configuracion, C::CON_CAPACIDAD, true);

        return array_filter([
            'NUMNITEMPRESATRANSPORTE' => C::digitos($empresa->nit),
            'NUMPLACA' => $this->placa,
            'CODCONFIGURACIONUNIDADCARGA' => $this->configuracion,
            'CODMARCAVEHICULOCARGA' => $this->marca_codigo,
            'NUMEJES' => $ejes ? $this->numero_ejes : null,
            'ANOFABRICACIONVEHICULOCARGA' => $this->modelo,
            'PESOVEHICULOVACIO' => $this->peso_vacio,
            'CAPACIDADUNIDADCARGA' => $capacidad ? $this->capacidad : null,
            'UNIDADMEDIDACAPACIDAD' => $this->unidad_capacidad,
            'CODTIPOCARROCERIA' => $this->carroceria_codigo,
            'CODTIPOIDPROPIETARIO' => C::tipoId($this->propietario?->tipoIdentificacion?->codigo),
            'NUMIDPROPIETARIO' => C::digitos($this->propietario?->documento),
            'CODTIPOIDTENEDOR' => C::tipoId($tenedor?->tipoIdentificacion?->codigo),
            'NUMIDTENEDOR' => C::digitos($tenedor?->documento),
        ], fn ($v) => $v !== null && $v !== '');
    }

    public function validarRndc(): array
    {
        $e = ValidarVehiculo::comunes($this->placa, $this->configuracion, $this->marca_codigo, $this->modelo,
            $this->peso_vacio, $this->capacidad, $this->numero_ejes, $this->propietario, $this->tenedor ?? $this->propietario);
        if (! preg_match('/^R\d{5}$/', (string) $this->placa)) {
            $e[] = 'La placa de un remolque o semirremolque es R seguida de 5 números.';
        }
        if (! isset(C::CONFIGURACIONES_REMOLQUE[$this->configuracion])) {
            $e[] = 'La configuración no es de semirremolque, remolque o remolque balanceado.';
        }

        return $e;
    }
}
