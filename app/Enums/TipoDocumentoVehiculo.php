<?php

namespace App\Enums;

use App\Enums\Contratos\TipoDocumento;

/**
 * Documentos que se controlan por vehículo. Todos son obligatorios para operar
 * en transporte especial; cada renovación es un registro nuevo (historial).
 */
enum TipoDocumentoVehiculo: string implements TipoDocumento
{
    case Soat = 'soat';
    case Tecnomecanica = 'tecnomecanica';
    case RevisionPreventiva = 'revision_preventiva';
    case PolizaContractual = 'poliza_rcc';
    case PolizaExtracontractual = 'poliza_rce';
    case TarjetaOperacion = 'tarjeta_operacion';

    public function getLabel(): string
    {
        return match ($this) {
            self::Soat => 'SOAT',
            self::Tecnomecanica => 'Revisión técnico-mecánica',
            self::RevisionPreventiva => 'Revisión preventiva (bimestral)',
            self::PolizaContractual => 'Póliza RC contractual',
            self::PolizaExtracontractual => 'Póliza RC extracontractual',
            self::TarjetaOperacion => 'Tarjeta de operación',
        };
    }

    /** Nombre corto para encabezados de tabla. */
    public function corto(): string
    {
        return match ($this) {
            self::Soat => 'SOAT',
            self::Tecnomecanica => 'Técnico-mecánica',
            self::RevisionPreventiva => 'Preventiva',
            self::PolizaContractual => 'RCC',
            self::PolizaExtracontractual => 'RCE',
            self::TarjetaOperacion => 'T. operación',
        };
    }

    /** Lo expide una aseguradora (se elige del catálogo). */
    public function usaAseguradora(): bool
    {
        return in_array($this, [self::Soat, self::PolizaContractual, self::PolizaExtracontractual], true);
    }

    /** Etiqueta del campo "entidad" cuando no es una aseguradora. */
    public function etiquetaEntidad(): string
    {
        return match ($this) {
            self::Tecnomecanica => 'CDA',
            self::RevisionPreventiva => 'Taller o centro de diagnóstico',
            self::TarjetaOperacion => 'Entidad que la expide',
            default => 'Entidad',
        };
    }

    /** Meses de vigencia habituales, para sugerir el vencimiento a partir de la expedición. */
    public function mesesVigencia(): ?int
    {
        return match ($this) {
            self::RevisionPreventiva => 2,
            self::TarjetaOperacion => 24,
            default => 12,
        };
    }

    public function obligatorio(): bool
    {
        return true;
    }

    public function diasAviso(): ?int
    {
        return $this === self::RevisionPreventiva ? 10 : null;
    }

    /** @return list<self> */
    public static function obligatorios(): array
    {
        return array_values(array_filter(self::cases(), fn (self $t) => $t->obligatorio()));
    }
}
