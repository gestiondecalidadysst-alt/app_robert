<?php

namespace App\Filament\Compartido;

use App\Models\TipoIdentificacion;
use App\Support\DireccionesTerritoriales;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

/**
 * Campos de los datos de una empresa: usados por el superadministrador (/admin)
 * y por la propia empresa en "Mi empresa" (/app).
 */
final class FormularioEmpresa
{
    public static function componentes(): array
    {
        return [
            Section::make('Identificación')
                ->columnSpanFull()
                ->icon('heroicon-o-identification')
                ->columns(['default' => 1, 'md' => 6])
                ->schema([
                    Select::make('tipo_identificacion_id')
                        ->label('Tipo de identificación')
                        ->options(fn () => TipoIdentificacion::query()->activos()->orderBy('nombre')->pluck('nombre', 'id'))
                        ->default(fn () => TipoIdentificacion::firstWhere('codigo', 'NIT')?->id)
                        ->columnSpan(['md' => 2]),
                    ...array_map(
                        fn ($campo) => $campo->columnSpan(['md' => $campo->getName() === 'dv' ? 1 : 3]),
                        CamposComunes::nit(),
                    ),
                    TextInput::make('razon_social')
                        ->label('Razón social')
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(['md' => 3]),
                    TextInput::make('nombre_comercial')
                        ->label('Nombre comercial')
                        ->helperText('Es el nombre que se muestra en la plataforma.')
                        ->maxLength(255)
                        ->columnSpan(['md' => 3]),
                ]),

            Section::make('Contacto')
                ->columnSpanFull()
                ->icon('heroicon-o-map-pin')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    CamposComunes::municipio(),
                    TextInput::make('direccion')->label('Dirección')->maxLength(255),
                    TextInput::make('telefono')->label('Teléfono')->tel()->maxLength(30),
                    TextInput::make('email')->label('Correo electrónico')->email()->maxLength(255),
                    TextInput::make('sitio_web')->label('Sitio web')->maxLength(255)->placeholder('www.suempresa.co')
                        ->helperText('Se cita en la cláusula de tratamiento de datos del contrato.'),
                ]),

            Grid::make(['default' => 1, 'lg' => 2])->columnSpanFull()->schema([
                Section::make('Representante legal')
                    ->icon('heroicon-o-user')
                    ->schema([
                        TextInput::make('representante_legal')->label('Nombre completo')->maxLength(255),
                        Grid::make(2)->schema([
                            TextInput::make('representante_documento')->label('Cédula')->maxLength(20),
                            TextInput::make('representante_expedicion')->label('Expedida en')->maxLength(100),
                        ]),
                        FileUpload::make('firma_representante_path')
                            ->label('Firma escaneada')
                            ->image()
                            ->disk('local')
                            ->visibility('private')
                            ->directory(fn () => 'firmas-representante/' . (filament()->getTenant()?->getKey() ?? 'plataforma'))
                            ->acceptedFileTypes(['image/png', 'image/jpeg'])
                            ->maxSize(512)
                            ->helperText('PNG con fondo transparente o blanco. Es privada: solo se estampa en los contratos emitidos.'),
                    ]),
                Section::make('Habilitación como transportador')
                    ->icon('heroicon-o-document-check')
                    ->schema([
                        Select::make('codigo_territorial')
                            ->label('Dirección territorial que otorgó la habilitación')
                            ->options(DireccionesTerritoriales::opciones())
                            ->searchable()
                            ->helperText('Su código son los 3 primeros dígitos del número del FUEC.'),
                        Grid::make(2)->schema([
                            TextInput::make('resolucion_habilitacion')->label('No. de resolución de habilitación')->maxLength(20)
                                ->helperText('Solo el número, por ejemplo 0155.'),
                            TextInput::make('anio_habilitacion')->label('Año de habilitación')->numeric()->minValue(1950)->maxValue(2100),
                        ]),
                    ]),
            ]),

            Section::make('Logo e imágenes de los documentos')
                ->columnSpanFull()
                ->icon('heroicon-o-photo')
                ->schema([
                    FileUpload::make('logo_path')
                        ->label('Logo de la empresa')
                        ->image()
                        ->disk('public')
                        ->directory('logos')
                        ->maxSize(1024)
                        ->helperText('PNG o JPG, máximo 1 MB. Aparece en los documentos y en el selector de empresa.'),
                    FileUpload::make('pie_documentos_path')
                        ->label('Imagen del pie de página de los contratos')
                        ->image()
                        ->disk('public')
                        ->directory('pies-documento')
                        ->maxSize(1024)
                        ->helperText('Franja con certificaciones y sellos (ICONTEC, Vigilado SuperTransporte…). Se ajusta al ancho de la página.'),
                ]),
        ];
    }
}
