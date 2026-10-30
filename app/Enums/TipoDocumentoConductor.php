<?php

namespace App\Enums;

use App\Enums\Contratos\TipoDocumento;

/**
 * Documentos que se controlan por conductor. Licencia y seguridad social son obligatorios
 * para expedir un FUEC (como en el legado); los demás se controlan con semáforo sin bloquear.
 */
enum TipoDocumentoConductor: string implements TipoDocumento
{
    case Licencia = 'licencia';
    case SeguridadSocial = 'seguridad_social';
    case ExamenMedico = 'examen_medico';
    case ManejoDefensivo = 'manejo_defensivo';
    case Contrato = 'contrato';

    public function getLabel(): string
    {
        return match ($this) {
            self::Licencia => 'Licencia de conducción',
            self::SeguridadSocial => 'Seguridad social (planilla)',
            self::ExamenMedico => 'Examen médico ocupacional',
            self::ManejoDefensivo => 'Curso de manejo defensivo',
            self::Contrato => 'Contrato',
        };
    }

    public function corto(): string
    {
        return match ($this) {
            self::Licencia => 'Licencia',
            self::SeguridadSocial => 'Seg. social',
            self::ExamenMedico => 'Examen médico',
            self::ManejoDefensivo => 'Manejo defensivo',
            self::Contrato => 'Contrato',
        };
    }

    public function obligatorio(): bool
    {
        return $this === self::Licencia || $this === self::SeguridadSocial;
    }

    public function mesesVigencia(): ?int
    {
        return match ($this) {
            self::Licencia => 36,
            self::SeguridadSocial => 1,
            self::ExamenMedico, self::ManejoDefensivo, self::Contrato => 12,
        };
    }

    public function diasAviso(): ?int
    {
        return $this === self::SeguridadSocial ? 5 : null;
    }

    /** Etiqueta del campo "entidad". */
    public function etiquetaEntidad(): string
    {
        return match ($this) {
            self::Licencia => 'Organismo de tránsito que la expide',
            self::SeguridadSocial => 'Operador de la planilla',
            self::ExamenMedico => 'IPS',
            self::ManejoDefensivo => 'Entidad que dictó el curso',
            self::Contrato => 'Tipo de contrato',
        };
    }
}
