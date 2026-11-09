<?php

namespace App\Filament\App\Resources\Cotizaciones\Schemas;

use App\Models\Cliente;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Formulario para crear/editar una cotización: destinatario (cliente o prospecto), datos, líneas
 * Fecha/Ruta/Duración/Valor, descuento e IVA opcional.
 */
final class CotizacionForm
{
    public static function configure(Schema $schema): Schema
    {
        $validez = (int) (Filament::getTenant()?->cotizacionConfig()['validez_dias'] ?? 15);

        return $schema->components([
            Section::make('Destinatario')
                ->description('Elige un cliente registrado, o deja vacío y llena los datos del prospecto.')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Select::make('cliente_id')
                        ->label('Cliente')
                        ->options(fn () => Cliente::query()->orderBy('nombre')->pluck('nombre', 'id'))
                        ->searchable()
                        ->live()
                        ->placeholder('— Prospecto (no registrado) —')
                        ->columnSpanFull(),
                    TextInput::make('prospecto_nombre')->label('Nombre del prospecto')
                        ->required(fn (Get $get) => blank($get('cliente_id')))
                        ->visible(fn (Get $get) => blank($get('cliente_id')))->maxLength(255),
                    TextInput::make('prospecto_contacto')->label('Contacto')
                        ->visible(fn (Get $get) => blank($get('cliente_id')))->maxLength(255),
                    TextInput::make('prospecto_telefono')->label('Teléfono')
                        ->visible(fn (Get $get) => blank($get('cliente_id')))->maxLength(60),
                    TextInput::make('prospecto_email')->label('Correo')->email()
                        ->visible(fn (Get $get) => blank($get('cliente_id')))->maxLength(255),
                ]),

            Section::make('Datos de la cotización')
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextInput::make('ciudad')->label('Ciudad')
                        ->default(fn () => Filament::getTenant()?->municipio?->nombre)->maxLength(120),
                    DatePicker::make('fecha')->label('Fecha')->default(today())->required(),
                    DatePicker::make('valido_hasta')->label('Válida hasta')
                        ->default(fn () => today()->addDays($validez))->required()
                        ->helperText("Por defecto {$validez} días (configurable en Configuración → Cotizaciones)."),
                    Textarea::make('asunto')->label('Asunto')->rows(2)->columnSpanFull()
                        ->placeholder('traslados de transporte especial del 6 al 11 de octubre…'),
                ]),

            Section::make('Servicios')
                ->description('Cada fila es una línea de la tabla: Fecha · Ruta · Duración · Valor.')
                ->schema([
                    Repeater::make('items')
                        ->relationship()
                        ->label('')
                        ->columns(['default' => 1, 'md' => 12])
                        ->reorderable()
                        ->orderColumn('orden')
                        ->defaultItems(1)
                        ->addActionLabel('Agregar servicio')
                        ->schema([
                            TextInput::make('fecha_texto')->label('Fecha')->placeholder('6 de octubre')
                                ->columnSpan(['md' => 2]),
                            TextInput::make('ruta')->label('Ruta')->required()
                                ->placeholder('Aeropuerto Los Garzones – Hotel GHL')->columnSpan(['md' => 5]),
                            TextInput::make('detalle')->label('Duración / detalle')
                                ->placeholder('6 personas · 2 llegadas')->columnSpan(['md' => 3]),
                            TextInput::make('valor')->label('Valor')->numeric()->prefix('$')->required()
                                ->minValue(0)->columnSpan(['md' => 2]),
                        ]),
                ]),

            Section::make('Totales y condiciones')
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextInput::make('descuento')->label('Descuento comercial')->numeric()->prefix('$')
                        ->default(0)->minValue(0),
                    Toggle::make('aplica_iva')->label('Aplica IVA')->live()->inline(false),
                    TextInput::make('iva_porcentaje')->label('IVA %')->numeric()->default(19)
                        ->minValue(0)->maxValue(100)->visible(fn (Get $get) => (bool) $get('aplica_iva')),
                    Textarea::make('observaciones')->label('Observaciones')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }
}
