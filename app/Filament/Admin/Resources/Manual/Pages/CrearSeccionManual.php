<?php

namespace App\Filament\Admin\Resources\Manual\Pages;

use App\Filament\Admin\Resources\Manual\SeccionManualResource;
use Filament\Resources\Pages\CreateRecord;

class CrearSeccionManual extends CreateRecord
{
    protected static string $resource = SeccionManualResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
