<?php

namespace App\Filament\Admin\Resources\Manual\Pages;

use App\Filament\Admin\Resources\Manual\SeccionManualResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditarSeccionManual extends EditRecord
{
    protected static string $resource = SeccionManualResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
