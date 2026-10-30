<?php

namespace App\Filament\App\Resources\Contratos\Schemas;

use App\Enums\FormaPago;
use App\Enums\ObjetoContrato;
use App\Filament\App\Resources\Clientes\ClienteResource;
use App\Filament\Compartido\CamposComunes;
use App\Models\Cliente;
use App\Models\Oficina;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Formulario del contrato. El número lo asigna el sistema al guardar.
 */
final class ContratoForm
{
    public static function configure(Schema $schema): Schema
    {
        $columnas = ['default' => 1, 'md' => 3];

        return $schema->components([
            Section::make('Contratante y objeto')
                ->icon('heroicon-o-building-storefront')
                ->columnSpanFull()
                ->columns($columnas)
                ->schema([
                    Select::make('cliente_id')
                        ->label('Cliente (contratante)')
                        ->relationship('cliente', 'nombre', fn ($query) => $query->withoutTrashed()->activos()->orderBy('nombre'))
                        ->getOptionLabelFromRecordUsing(fn (Cliente $c) => "{$c->nombre} · {$c->documento}")
                        ->searchable(['nombre', 'documento'])
                        ->preload()
                        ->required()
                        ->createOptionForm(ClienteResource::campos())
                        ->createOptionModalHeading('Nuevo cliente')
                        ->columnSpan(['md' => 2]),
                    Select::make('objeto')
                        ->label('Objeto')
                        ->options(ObjetoContrato::class)
                        ->required(),
                    Textarea::make('descripcion_objeto')
                        ->label('Descripción del objeto')
                        ->rows(2)
                        ->helperText('En contratos escolares con entidades territoriales, incluya los establecimientos educativos (art. 6 Res. 6652/2019).')
                        ->columnSpanFull(),
                ]),

            Section::make('Duración y recorrido')
                ->icon('heroicon-o-map')
                ->columnSpanFull()
                ->columns($columnas)
                ->schema([
                    DatePicker::make('fecha_inicio')->label('Fecha de inicio')->required()->default(now()),
                    DatePicker::make('fecha_fin')->label('Fecha de terminación')->required()->afterOrEqual('fecha_inicio'),
                    Select::make('oficina_id')
                        ->label('Oficina')
                        ->options(fn () => Oficina::query()->where('activa', true)->orderBy('nombre')->pluck('nombre', 'id')),
                    TimePicker::make('hora_inicio')->label('Hora de inicio')->seconds(false),
                    TimePicker::make('hora_fin')->label('Hora de terminación')->seconds(false),
                    TextInput::make('cantidad_pasajeros')->label('Pasajeros')->numeric()->minValue(1),
                    CamposComunes::municipio('origen_municipio_id', 'Origen'),
                    CamposComunes::municipio('destino_municipio_id', 'Destino'),
                    TextInput::make('cantidad_vehiculos')->label('Vehículos')->numeric()->minValue(1),
                    Textarea::make('recorrido')->label('Ruta o recorrido')->rows(2)->columnSpanFull(),
                    Toggle::make('disposicion_total')
                        ->label('Disposición total del vehículo')
                        ->helperText('El FUEC debe indicar las zonas y municipios donde se presta (art. 9).')
                        ->live()
                        ->inline(false),
                    Textarea::make('zonas_disposicion')
                        ->label('Zonas y municipios de prestación')
                        ->rows(2)
                        ->visible(fn (Get $get) => (bool) $get('disposicion_total'))
                        ->required(fn (Get $get) => (bool) $get('disposicion_total'))
                        ->columnSpan(['md' => 2]),
                ]),

            Section::make('Responsable del contrato')
                ->description('Persona del contratante responsable del servicio.')
                ->icon('heroicon-o-user')
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 4])
                ->collapsible()
                ->schema([
                    TextInput::make('responsable_nombre')->label('Nombre')->maxLength(255),
                    TextInput::make('responsable_documento')->label('Documento')->maxLength(20),
                    TextInput::make('responsable_telefono')->label('Teléfono')->tel()->maxLength(30),
                    TextInput::make('responsable_direccion')->label('Dirección')->maxLength(255),
                ]),

            Section::make('Valor y forma de pago')
                ->icon('heroicon-o-banknotes')
                ->columnSpanFull()
                ->columns($columnas)
                ->schema([
                    TextInput::make('valor')
                        ->label('Valor del contrato')
                        ->numeric()
                        ->prefix('$')
                        ->minValue(0)
                        ->required(),
                    Select::make('forma_pago')
                        ->label('Forma de pago')
                        ->options(FormaPago::class)
                        ->default(FormaPago::Contado->value)
                        ->required()
                        ->live(),
                    DatePicker::make('fecha_limite_pago')
                        ->label('Pagar a más tardar')
                        ->visible(fn (Get $get) => in_array($get('forma_pago'), [FormaPago::Credito, FormaPago::Credito->value], true)),
                    Textarea::make('observaciones')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }
}
