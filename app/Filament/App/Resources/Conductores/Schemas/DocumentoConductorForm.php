<?php

namespace App\Filament\App\Resources\Conductores\Schemas;

use App\Enums\TipoDocumentoConductor;
use App\Models\CategoriaLicencia;
use App\Models\DocumentoConductor;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Carbon;

/**
 * Campos del documento de un conductor. La categoría solo aplica a la licencia.
 */
final class DocumentoConductorForm
{
    public static function campos(bool $tipoFijo = false): array
    {
        $tipo = fn (Get $get): ?TipoDocumentoConductor => $get('tipo') instanceof TipoDocumentoConductor
            ? $get('tipo')
            : TipoDocumentoConductor::tryFrom((string) $get('tipo'));

        return [
            Select::make('tipo')
                ->label('Documento')
                ->options(TipoDocumentoConductor::class)
                ->required()
                ->live()
                ->disabled($tipoFijo)
                ->dehydrated(),
            TextInput::make('numero')
                ->label(fn (Get $get) => $tipo($get) === TipoDocumentoConductor::SeguridadSocial ? 'Número de planilla' : 'Número')
                ->maxLength(60),
            Select::make('categoria_licencia_id')
                ->label('Categoría')
                ->options(fn () => CategoriaLicencia::query()->activos()->orderBy('codigo')->get()->pluck('nombre_completo', 'id'))
                ->visible(fn (Get $get) => $tipo($get) === TipoDocumentoConductor::Licencia)
                ->required(fn (Get $get) => $tipo($get) === TipoDocumentoConductor::Licencia)
                ->helperText('El transporte especial exige categoría C (servicio público).'),
            TextInput::make('entidad')
                ->label(fn (Get $get) => $tipo($get)?->etiquetaEntidad() ?? 'Entidad')
                ->maxLength(255),
            DatePicker::make('fecha_expedicion')
                ->label(fn (Get $get) => $tipo($get) === TipoDocumentoConductor::SeguridadSocial ? 'Fecha de pago' : 'Fecha de expedición')
                ->maxDate(now())
                ->live(onBlur: true)
                ->afterStateUpdated(function (Get $get, Set $set, ?string $state) use ($tipo): void {
                    $meses = $tipo($get)?->mesesVigencia();
                    if ($state && $meses && blank($get('fecha_vencimiento'))) {
                        $set('fecha_vencimiento', Carbon::parse($state)->addMonths($meses)->subDay()->toDateString());
                    }
                }),
            DatePicker::make('fecha_vencimiento')
                ->label(fn (Get $get) => $tipo($get) === TipoDocumentoConductor::SeguridadSocial ? 'Cubre hasta' : 'Fecha de vencimiento')
                ->required()
                ->afterOrEqual('fecha_expedicion')
                ->helperText('Se sugiere al llenar la fecha anterior; revísela contra el documento.'),
            FileUpload::make('archivo_path')
                ->label('Archivo (PDF o imagen)')
                ->disk(DocumentoConductor::DISCO)
                ->directory(fn () => 'documentos-conductor/' . (filament()->getTenant()?->getKey() ?? 'sin-empresa'))
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
