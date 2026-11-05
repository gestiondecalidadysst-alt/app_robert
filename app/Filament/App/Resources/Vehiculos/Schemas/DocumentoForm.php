<?php

namespace App\Filament\App\Resources\Vehiculos\Schemas;

use App\Enums\TipoDocumentoVehiculo;
use App\Models\Aseguradora;
use App\Models\DocumentoVehiculo;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Carbon;

/**
 * Campos del documento de un vehículo. Se usan en la pestaña Documentos del vehículo
 * y en la acción "Renovar" de la pantalla Vencimientos.
 */
final class DocumentoForm
{
    public static function campos(bool $tipoFijo = false): array
    {
        $tipo = fn (Get $get): ?TipoDocumentoVehiculo => $get('tipo') instanceof TipoDocumentoVehiculo
            ? $get('tipo')
            : TipoDocumentoVehiculo::tryFrom((string) $get('tipo'));

        return [
            Select::make('tipo')
                ->label('Documento')
                ->options(TipoDocumentoVehiculo::class)
                ->required()
                ->live()
                ->disabled($tipoFijo)
                ->dehydrated(),
            TextInput::make('numero')->label('Número')->maxLength(60),
            Select::make('aseguradora_id')
                ->label('Aseguradora')
                ->options(fn () => Aseguradora::query()->activos()->orderBy('nombre')->pluck('nombre', 'id'))
                ->searchable()
                ->visible(fn (Get $get) => $tipo($get)?->usaAseguradora() ?? false)
                ->required(fn (Get $get) => $tipo($get)?->usaAseguradora() ?? false),
            TextInput::make('entidad')
                ->label(fn (Get $get) => $tipo($get)?->etiquetaEntidad() ?? 'Entidad')
                ->maxLength(255)
                ->visible(fn (Get $get) => ! ($tipo($get)?->usaAseguradora() ?? false)),
            DatePicker::make('fecha_expedicion')
                ->label('Fecha de expedición')
                ->maxDate(now())
                ->live(onBlur: true)
                ->afterStateUpdated(function (Get $get, Set $set, ?string $state) use ($tipo): void {
                    $meses = $tipo($get)?->mesesVigencia();
                    if ($state && $meses && blank($get('fecha_vencimiento'))) {
                        $set('fecha_vencimiento', Carbon::parse($state)->addMonths($meses)->subDay()->toDateString());
                    }
                }),
            DatePicker::make('fecha_vencimiento')
                ->label('Fecha de vencimiento')
                ->required()
                ->afterOrEqual('fecha_expedicion')
                ->helperText('Se sugiere al llenar la expedición; revísela contra el documento.'),
            FileUpload::make('archivo_path')
                ->label('Archivo (PDF o imagen)')
                ->disk(DocumentoVehiculo::DISCO)
                ->directory(fn () => 'documentos-vehiculo/'.(filament()->getTenant()?->getKey() ?? 'sin-empresa'))
                ->visibility('private')
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(5120)
                ->storeFileNamesIn('archivo_nombre')
                ->openable()
                ->downloadable()
                ->columnSpanFull(),
            Textarea::make('observacion')->label('Observación')->rows(2)->columnSpanFull(),
        ];
    }
}
