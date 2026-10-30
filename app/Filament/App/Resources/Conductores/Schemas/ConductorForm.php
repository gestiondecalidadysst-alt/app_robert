<?php

namespace App\Filament\App\Resources\Conductores\Schemas;

use App\Enums\EstadoComparendos;
use App\Filament\Compartido\CamposComunes;
use App\Models\Arl;
use App\Models\Conductor;
use App\Models\Eps;
use App\Models\NivelAcademico;
use App\Models\Oficina;
use App\Models\TipoIdentificacion;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Formulario del conductor: datos personales, seguridad social y comparendos.
 * El estado no se edita aquí (ver acción "Cambiar estado"); la licencia va en Documentos.
 */
final class ConductorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('conductor')
                ->columnSpanFull()
                ->persistTabInQueryString()
                ->tabs([
                    Tab::make('Datos personales')
                        ->icon('heroicon-o-user')
                        ->columns(['default' => 1, 'md' => 3])
                        ->schema(self::personales()),
                    Tab::make('Seguridad social')
                        ->icon('heroicon-o-heart')
                        ->columns(['default' => 1, 'md' => 3])
                        ->schema(self::seguridadSocial()),
                    Tab::make('Comparendos')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->columns(['default' => 1, 'md' => 3])
                        ->schema(self::comparendos()),
                ]),
        ]);
    }

    private static function personales(): array
    {
        return [
            Select::make('tipo_identificacion_id')
                ->label('Tipo de identificación')
                ->options(fn () => TipoIdentificacion::query()->activos()->where('es_empresa', false)->orderBy('nombre')->pluck('nombre', 'id'))
                ->default(fn () => TipoIdentificacion::firstWhere('codigo', 'CC')?->id)
                ->required(),
            TextInput::make('documento')
                ->label('Número de identificación')
                ->required()
                ->maxLength(20)
                ->scopedUnique(ignoreRecord: true)
                ->helperText('Sin puntos.'),
            FileUpload::make('foto_path')
                ->label('Foto')
                ->image()
                ->avatar()
                ->disk('public')
                ->directory('conductores')
                ->maxSize(1024),
            TextInput::make('nombres')->required()->maxLength(255),
            TextInput::make('apellidos')->required()->maxLength(255),
            DatePicker::make('fecha_nacimiento')->label('Fecha de nacimiento')->maxDate(now()->subYears(16)),
            Select::make('genero')->label('Género')->options(Conductor::GENEROS),
            Select::make('grupo_sanguineo')->label('Grupo sanguíneo (RH)')->options(array_combine(Conductor::GRUPOS_SANGUINEOS, Conductor::GRUPOS_SANGUINEOS)),
            TextInput::make('telefono')->label('Celular')->tel()->maxLength(30),
            TextInput::make('email')->label('Correo electrónico')->email()->maxLength(255),
            TextInput::make('direccion')->label('Dirección')->maxLength(255),
            CamposComunes::municipio(),
            Select::make('nivel_academico_id')
                ->label('Nivel académico')
                ->options(fn () => NivelAcademico::query()->activos()->orderBy('nombre')->pluck('nombre', 'id')),
            Select::make('oficina_id')
                ->label('Oficina')
                ->options(fn () => Oficina::query()->where('activa', true)->orderBy('nombre')->pluck('nombre', 'id')),
            DatePicker::make('fecha_ingreso')->label('Fecha de ingreso')->default(now()),
            TextInput::make('contacto_emergencia')->label('Contacto de emergencia')->maxLength(255),
            TextInput::make('telefono_emergencia')->label('Teléfono de emergencia')->tel()->maxLength(30),
            Textarea::make('observaciones')->rows(2)->columnSpanFull(),
        ];
    }

    private static function seguridadSocial(): array
    {
        return [
            Select::make('eps_id')
                ->label('EPS')
                ->options(fn () => Eps::query()->activos()->orderBy('nombre')->pluck('nombre', 'id'))
                ->searchable(),
            Select::make('arl_id')
                ->label('ARL')
                ->options(fn () => Arl::query()->activos()->orderBy('nombre')->pluck('nombre', 'id'))
                ->searchable(),
            TextInput::make('fondo_pensiones')->label('Fondo de pensiones')->maxLength(255),
            Text::make('El pago mensual de la planilla (con su vencimiento) se registra en la pestaña Documentos de la hoja de vida.')
                ->color('gray')
                ->columnSpanFull(),
        ];
    }

    private static function comparendos(): array
    {
        return [
            Select::make('comparendos_estado')
                ->label('Situación')
                ->options(EstadoComparendos::class)
                ->live(),
            TextInput::make('comparendos_cantidad')
                ->label('Cantidad')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->visible(fn (Get $get) => ! in_array($get('comparendos_estado'), [null, '', EstadoComparendos::SinComparendos, EstadoComparendos::SinComparendos->value], true)),
            DatePicker::make('comparendos_consultado_el')
                ->label('Fecha de consulta en el SIMIT')
                ->maxDate(now())
                ->required(fn (Get $get) => filled($get('comparendos_estado'))),
        ];
    }
}
