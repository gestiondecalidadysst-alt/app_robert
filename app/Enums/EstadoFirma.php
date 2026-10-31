<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Estado del documento del contrato en el proceso de firma. "Vencido" no se guarda:
 * se calcula cuando el enlace venció sin firma (DocumentoContrato::estadoFirma()).
 */
enum EstadoFirma: string implements HasColor, HasIcon, HasLabel
{
    case Emitido = 'emitido';
    case Enviado = 'enviado';
    case Visto = 'visto';
    case Firmado = 'firmado';
    case Vencido = 'vencido';
    case Anulado = 'anulado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Emitido => 'Emitido',
            self::Enviado => 'Enviado para firma',
            self::Visto => 'Visto por el cliente',
            self::Firmado => 'Firmado',
            self::Vencido => 'Enlace vencido',
            self::Anulado => 'Anulado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Emitido => 'gray',
            self::Enviado => 'info',
            self::Visto => 'warning',
            self::Firmado => 'success',
            self::Vencido, self::Anulado => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Emitido => 'heroicon-m-document-text',
            self::Enviado => 'heroicon-m-paper-airplane',
            self::Visto => 'heroicon-m-eye',
            self::Firmado => 'heroicon-m-check-badge',
            self::Vencido => 'heroicon-m-clock',
            self::Anulado => 'heroicon-m-x-circle',
        };
    }
}
