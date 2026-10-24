<?php

namespace App\Filament\Compartido;

use App\Models\Municipio;
use App\Support\Nit;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Campos de formulario que se repiten en varios módulos.
 */
final class CamposComunes
{
    /** Selector de municipio con búsqueda en servidor (1.120 municipios DANE). */
    public static function municipio(string $campo = 'municipio_id', string $etiqueta = 'Municipio'): Select
    {
        return Select::make($campo)
            ->label($etiqueta)
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => Municipio::query()
                ->with('departamento')
                ->activos()
                ->where(fn ($q) => $q->where('nombre', 'like', "%{$search}%")
                    ->orWhereHas('departamento', fn ($d) => $d->where('nombre', 'like', "%{$search}%")))
                ->orderBy('nombre')
                ->limit(30)
                ->get()
                ->mapWithKeys(fn (Municipio $m) => [$m->id => $m->nombre_completo])
                ->all())
            ->getOptionLabelUsing(fn ($value): ?string => Municipio::with('departamento')->find($value)?->nombre_completo)
            ->placeholder('Escriba para buscar');
    }

    /** NIT con cálculo automático del dígito de verificación en el campo "dv". */
    public static function nit(string $campo = 'nit', string $campoDv = 'dv'): array
    {
        return [
            TextInput::make($campo)
                ->label('NIT')
                ->required()
                ->maxLength(15)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, ?string $state) => $set($campoDv, Nit::digitoVerificacion($state)))
                ->dehydrateStateUsing(fn (?string $state) => Nit::limpiar($state))
                ->helperText('Sin dígito de verificación.'),
            TextInput::make($campoDv)
                ->label('DV')
                ->disabled()
                ->dehydrated(false)
                ->formatStateUsing(fn (Get $get, $state) => $state ?? Nit::digitoVerificacion($get($campo)))
                ->helperText('Se calcula automáticamente.'),
        ];
    }
}
