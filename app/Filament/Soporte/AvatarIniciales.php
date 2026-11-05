<?php

namespace App\Filament\Soporte;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Avatar con las iniciales, generado localmente (no envía nombres a servicios externos).
 */
class AvatarIniciales implements AvatarProvider
{
    public function get(Model $record): string
    {
        $nombre = Filament::getNameForDefaultAvatar($record);

        $iniciales = Str::of($nombre)
            ->replaceMatches('/[^\pL\s]/u', ' ')
            ->squish()
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $parte) => mb_strtoupper(mb_substr($parte, 0, 1)))
            ->join('');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" rx="32" fill="#2A3E51"/>'
            .'<text x="50%" y="50%" dy=".35em" text-anchor="middle" font-family="Inter,Arial,sans-serif" font-size="24" font-weight="700" fill="#FFFFFF">'
            .e($iniciales ?: '?')
            .'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
