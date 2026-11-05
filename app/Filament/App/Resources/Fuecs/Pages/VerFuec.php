<?php

namespace App\Filament\App\Resources\Fuecs\Pages;

use App\Filament\App\Resources\Fuecs\Acciones\AccionesFuec;
use App\Filament\App\Resources\Fuecs\FuecResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class VerFuec extends ViewRecord
{
    protected static string $resource = FuecResource::class;

    public function getTitle(): string
    {
        return 'FUEC '.$this->getRecord()->placa.' · '.$this->getRecord()->datos['contrato']['contratante'];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('verificar')
                ->label('Ver verificación')
                ->icon('heroicon-o-qr-code')
                ->color('gray')
                ->url(fn () => $this->getRecord()->urlVerificacion())
                ->openUrlInNewTab(),
            AccionesFuec::anular()->after(fn () => $this->getRecord()->refresh()),
            AccionesFuec::pdf(),
        ];
    }
}
