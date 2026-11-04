<?php

namespace App\Filament\App\Resources\Vehiculos\Schemas;

use App\Filament\App\Resources\Propietarios\PropietarioResource;
use App\Filament\Compartido\CamposComunes;
use App\Models\Carroceria;
use App\Models\ClaseVehiculo;
use App\Models\Combustible;
use App\Models\EmpresaConvenio;
use App\Models\Linea;
use App\Models\Marca;
use App\Models\Propietario;
use App\Models\TipoServicio;
use App\Models\Vehiculo;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/**
 * Formulario del vehículo: identificación, tarjeta de propiedad, ficha técnica y vinculación.
 * El estado no se edita aquí (ver acción "Cambiar estado").
 */
final class VehiculoForm
{
    /** Placa colombiana: 3 letras + 3 números (carro) o 3 letras + 2 números + letra (moto). */
    public const PATRON_PLACA = '/^[A-Z]{3}[0-9]{2}[0-9A-Z]$/';

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('vehiculo')
                ->columnSpanFull()
                ->persistTabInQueryString()
                ->tabs([
                    Tab::make('Identificación')
                        ->icon('heroicon-o-identification')
                        ->columns(['default' => 1, 'md' => 3])
                        ->schema(self::identificacion()),
                    Tab::make('Tarjeta de propiedad')
                        ->icon('heroicon-o-document-text')
                        ->columns(['default' => 1, 'md' => 3])
                        ->schema(self::tarjetaPropiedad()),
                    Tab::make('Ficha técnica')
                        ->icon('heroicon-o-cog-6-tooth')
                        ->columns(['default' => 1, 'md' => 3])
                        ->schema(self::fichaTecnica()),
                    Tab::make('Vinculación')
                        ->icon('heroicon-o-link')
                        ->columns(['default' => 1, 'md' => 3])
                        ->schema(self::vinculacion()),
                    Tab::make('Carga (RNDC)')
                        ->icon('heroicon-o-cube')
                        ->visible(fn () => auth()->user()?->can('ver_rndc') ?? false)
                        ->columns(['default' => 1, 'md' => 3])
                        ->schema(self::carga()),
                ]),
        ]);
    }

    /** Datos que pide el RNDC para registrar el vehículo (camión rígido o tractocamión). */
    private static function carga(): array
    {
        $c = \App\Support\Carga\CatalogoRndc::class;
        $propietarios = fn () => \App\Models\Propietario::query()->where('activo', true)->orderBy('nombres')->get()
            ->mapWithKeys(fn ($p) => [$p->id => $p->nombre_completo . ' · ' . $p->documento]);

        return [
            \Filament\Schemas\Components\Text::make('Solo para vehículos de carga. Los códigos de marca, línea, color, carrocería y aseguradora son los de las tablas del anexo del RNDC. La capacidad se toma de «Ficha técnica».')->columnSpanFull()->color('gray'),
            Select::make('rndc_configuracion')->label('Configuración')->options($c::CONFIGURACIONES_VEHICULO)->live()
                ->helperText('Vacío = no es vehículo de carga.'),
            TextInput::make('numero_ejes')->label('Número de ejes')->numeric()->minValue(1)->maxValue(20)
                ->visible(fn ($get) => in_array((int) $get('rndc_configuracion'), $c::CON_EJES, true)),
            TextInput::make('peso_vacio')->label('Peso vacío (kg)')->numeric()->minValue(201)->maxValue(52999),
            Select::make('unidad_capacidad')->label('Unidad de la capacidad')->options($c::UNIDADES_CAPACIDAD)->default(1),
            TextInput::make('rndc_marca')->label('Código RNDC de la marca')->maxLength(10),
            TextInput::make('rndc_linea')->label('Código RNDC de la línea')->maxLength(10),
            TextInput::make('rndc_color')->label('Código RNDC del color')->maxLength(5),
            TextInput::make('rndc_carroceria')->label('Código RNDC de la carrocería')->maxLength(3)
                ->helperText('En tractocamiones se envía S.R.S. (0) automáticamente.'),
            Select::make('rndc_combustible')->label('Combustible')->options($c::COMBUSTIBLES),
            TextInput::make('ano_repotenciacion')->label('Año de repotenciación')->numeric()->minValue(1901)->maxValue((int) date('Y') + 1),
            TextInput::make('soat_nit_aseguradora')->label('NIT de la aseguradora del SOAT')->maxLength(11)
                ->helperText('Si la aseguradora del SOAT ya tiene NIT en el catálogo, se toma de allí.'),
            Select::make('tenedor_id')->label('Tenedor (poseedor)')->searchable()->options($propietarios)
                ->helperText('Vacío = el mismo propietario.'),
        ];
    }

    private static function identificacion(): array
    {
        return [
            TextInput::make('placa')
                ->required()
                ->maxLength(8)
                ->placeholder('ABC123')
                ->extraInputAttributes(['class' => 'ruta-placa-input'])
                ->dehydrateStateUsing(fn (?string $state) => Vehiculo::normalizarPlaca($state))
                ->rules([
                    fn (?Model $record): Closure => function (string $attribute, $value, Closure $fail) use ($record): void {
                        $placa = Vehiculo::normalizarPlaca($value);

                        if (! preg_match(self::PATRON_PLACA, $placa)) {
                            $fail('La placa debe tener el formato ABC123.');

                            return;
                        }

                        $repetida = Vehiculo::query()->withTrashed()
                            ->where('placa', $placa)
                            ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                            ->exists();

                        if ($repetida) {
                            $fail("La placa {$placa} ya está registrada en esta empresa.");
                        }
                    },
                ]),
            TextInput::make('numero_interno')
                ->label('Número interno')
                ->maxLength(10)
                ->scopedUnique(ignoreRecord: true),
            Select::make('propietario_id')
                ->label('Propietario')
                ->relationship('propietario', 'nombres', fn ($query) => $query->withoutTrashed()->activos()->orderBy('nombres'))
                ->getOptionLabelFromRecordUsing(fn (Propietario $p) => "{$p->nombre_completo} · {$p->documento}")
                ->searchable(['nombres', 'apellidos', 'documento'])
                ->preload()
                ->required()
                ->createOptionForm(PropietarioResource::campos())
                ->createOptionModalHeading('Nuevo propietario'),
            FileUpload::make('foto_path')
                ->label('Foto')
                ->image()
                ->disk('public')
                ->directory('vehiculos')
                ->maxSize(2048)
                ->columnSpanFull(),
            Textarea::make('observaciones')->rows(2)->columnSpanFull(),
        ];
    }

    private static function tarjetaPropiedad(): array
    {
        return [
            Select::make('marca_id')
                ->label('Marca')
                ->options(fn () => Marca::query()->activos()->orderBy('nombre')->pluck('nombre', 'id'))
                ->searchable()
                ->required()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('linea_id', null)),
            Select::make('linea_id')
                ->label('Línea')
                ->options(fn (Get $get) => filled($get('marca_id'))
                    ? Linea::query()->activos()->where('marca_id', $get('marca_id'))->orderBy('nombre')->pluck('nombre', 'id')
                    : [])
                ->searchable()
                ->disabled(fn (Get $get) => blank($get('marca_id')))
                ->helperText(fn (Get $get) => blank($get('marca_id')) ? 'Elija primero la marca.' : null),
            TextInput::make('modelo')
                ->label('Modelo (año)')
                ->numeric()
                ->required()
                ->minValue(1970)
                ->maxValue((int) now()->format('Y') + 1),
            Select::make('clase_vehiculo_id')
                ->label('Clase')
                ->options(fn () => ClaseVehiculo::query()->activos()->orderBy('nombre')->pluck('nombre', 'id'))
                ->searchable()
                ->required()
                ->helperText('Define el valor de administración.'),
            Select::make('carroceria_id')
                ->label('Carrocería')
                ->options(fn () => Carroceria::query()->activos()->orderBy('nombre')->pluck('nombre', 'id'))
                ->searchable(),
            Select::make('combustible_id')
                ->label('Combustible')
                ->options(fn () => Combustible::query()->activos()->orderBy('nombre')->pluck('nombre', 'id')),
            Select::make('tipo_servicio_id')
                ->label('Servicio')
                ->options(fn () => TipoServicio::query()->activos()->orderBy('nombre')->pluck('nombre', 'id')),
            TextInput::make('color')->maxLength(60),
            TextInput::make('cilindraje')->label('Cilindraje (cc)')->numeric()->minValue(0),
            TextInput::make('capacidad_pasajeros')->label('Capacidad (pasajeros)')->numeric()->minValue(1)->maxValue(100),
            TextInput::make('capacidad_kg')->label('Capacidad (kg)')->numeric()->minValue(0),
            TextInput::make('puertas')->numeric()->minValue(0)->maxValue(10),
            TextInput::make('numero_motor')->label('Número de motor')->maxLength(40),
            TextInput::make('vin')->label('VIN')->maxLength(40),
            TextInput::make('numero_chasis')->label('Número de chasis')->maxLength(40),
            DatePicker::make('fecha_matricula')->label('Fecha de matrícula')->maxDate(now()),
            CamposComunes::municipio('organismo_transito_id', 'Organismo de tránsito'),
        ];
    }

    private static function fichaTecnica(): array
    {
        $campos = [];

        foreach (Vehiculo::FICHA_TECNICA as $clave => $etiqueta) {
            $campo = TextInput::make("ficha_tecnica.{$clave}")->label($etiqueta)->maxLength(80);

            $campos[] = in_array($clave, ['potencia_hp', 'numero_ejes'], true)
                ? $campo->numeric()->minValue(0)
                : $campo;
        }

        return $campos;
    }

    private static function vinculacion(): array
    {
        return [
            Select::make('tipo_vinculacion')
                ->label('Tipo de vinculación')
                ->options(EmpresaConvenio::TIPOS_VINCULACION)
                ->live(),
            Select::make('empresa_convenio_id')
                ->label('Empresa en convenio')
                ->options(fn () => EmpresaConvenio::query()->where('activo', true)->orderBy('razon_social')->pluck('razon_social', 'id'))
                ->searchable()
                ->visible(fn (Get $get) => $get('tipo_vinculacion') === 'convenio_empresarial')
                ->required(fn (Get $get) => $get('tipo_vinculacion') === 'convenio_empresarial'),
            DatePicker::make('fecha_vinculacion')->label('Fecha de vinculación')->default(now()),
        ];
    }
}
