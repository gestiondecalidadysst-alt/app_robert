<?php

namespace App\Filament\App\Resources\RemesasCarga\Schemas;

use App\Models\RemesaCarga;
use App\Models\TerceroCarga;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;

/** Formulario de remesa como asistente por pasos: carga → partes → póliza → tiempos. */
final class RemesaCargaForm
{
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Wizard::make([
                self::pasoCarga(),
                self::pasoPartes(),
                self::pasoPoliza(),
                self::pasoTiempos(),
            ])->columnSpanFull()->skippable(false)->submitAction(view('filament.app.carga.boton-expedir-remesa')),
        ]);
    }

    private static function pasoCarga(): Step
    {
        return Step::make('Carga')
            ->icon('heroicon-o-cube')
            ->description('¿Qué se transporta?')
            ->columns(['default' => 1, 'md' => 2])
            ->schema([
                DatePicker::make('fecha')->required()->default(today())->native(false),
                Select::make('operacion')->label('Tipo de operación')->options(RemesaCarga::OPERACIONES)->default('G')->required()->live()->native(false),
                Select::make('naturaleza')->label('Naturaleza de la carga')->options(RemesaCarga::NATURALEZAS)->default(1)->required()->native(false),
                TextInput::make('producto_descripcion')->label('Producto (descripción corta)')->required()->maxLength(60)
                    ->placeholder('Por ejemplo, Aceite de bacalao'),
                TextInput::make('producto_codigo')->label('Código del producto')->required()->maxLength(6)
                    ->helperText('Tabla «Codificación de Productos» del anexo RNDC. Contenedor vacío = 009990, paqueteo = 009880.'),
                TextInput::make('empaque_codigo')->label('Código del empaque')->required()->maxLength(2)
                    ->helperText('Tabla «Empaques» del anexo RNDC.'),
                TextInput::make('cantidad')->label('Cantidad cargada')->numeric()->minValue(1)->maxValue(999999)
                    ->hidden(fn (Get $get) => $get('operacion') === 'V')->required(fn (Get $get) => $get('operacion') !== 'V'),
                Select::make('unidad')->label('Unidad')->options([1 => 'Kilogramos', 2 => 'Galones'])->default(1)->native(false)
                    ->hidden(fn (Get $get) => $get('operacion') === 'V'),
                TextInput::make('peso_contenedor_vacio')->label('Peso del contenedor vacío (kg)')->numeric()
                    ->visible(fn (Get $get) => in_array($get('operacion'), ['C', 'V'])),
            ]);
    }

    private static function pasoPartes(): Step
    {
        $terceros = fn () => TerceroCarga::query()->where('activo', true)->orderBy('nombre')->get()
            ->mapWithKeys(fn (TerceroCarga $t) => [$t->id => $t->nombre_completo.' · '.($t->tipoIdentificacion?->codigo ?? '').' '.$t->documento]);

        return Step::make('Remitente y destinatario')
            ->icon('heroicon-o-users')
            ->description('¿Quién envía y quién recibe?')
            ->columns(['default' => 1, 'md' => 2])
            ->schema([
                Select::make('remitente_id')->label('Remitente')->required()->searchable()->native(false)->options($terceros)
                    ->helperText('Si no está en la lista, créelo primero en Carga → Terceros.'),
                Select::make('destinatario_id')->label('Destinatario')->required()->searchable()->native(false)->options($terceros)
                    ->helperText('Remitente y destinatario no pueden estar en el mismo municipio (salvo desechos peligrosos o contenedor vacío).'),
            ]);
    }

    private static function pasoPoliza(): Step
    {
        return Step::make('Póliza de la carga')
            ->icon('heroicon-o-shield-check')
            ->description('¿Está asegurada la carga?')
            ->columns(['default' => 1, 'md' => 2])
            ->schema([
                Select::make('dueno_poliza')->label('Tomador de la póliza')->options(RemesaCarga::DUENOS_POLIZA)->default('N')->required()->live()->native(false)
                    ->columnSpanFull()->helperText('Si no hay póliza, deje «No existe» y puede saltar al siguiente paso.'),
                TextInput::make('numero_poliza')->label('Número de la póliza')->maxLength(20)
                    ->required(fn (Get $get) => $get('dueno_poliza') !== 'N')->visible(fn (Get $get) => $get('dueno_poliza') !== 'N'),
                DatePicker::make('vence_poliza')->label('Vence la póliza')->native(false)
                    ->required(fn (Get $get) => $get('dueno_poliza') !== 'N')->visible(fn (Get $get) => $get('dueno_poliza') !== 'N'),
                TextInput::make('aseguradora_nit')->label('NIT de la aseguradora')->maxLength(11)
                    ->required(fn (Get $get) => $get('dueno_poliza') !== 'N')->visible(fn (Get $get) => $get('dueno_poliza') !== 'N')
                    ->helperText('Tabla «Empresas Aseguradoras» del anexo RNDC.'),
            ]);
    }

    private static function pasoTiempos(): Step
    {
        return Step::make('Tiempos')
            ->icon('heroicon-o-clock')
            ->description('Pactos de cargue y descargue, y tiempos reales si ya cargó.')
            ->columns(['default' => 1, 'md' => 2])
            ->schema([
                TextInput::make('horas_pacto_cargue')->label('Horas pactadas para cargue')->numeric()->minValue(0)->maxValue(999)->default(12)->suffix('h'),
                TextInput::make('minutos_pacto_cargue')->label('Minutos adicionales')->numeric()->minValue(0)->maxValue(59)->default(0)->suffix('min'),
                TextInput::make('horas_pacto_descargue')->label('Horas pactadas para descargue')->numeric()->minValue(0)->maxValue(999)->default(12)->suffix('h'),
                TextInput::make('minutos_pacto_descargue')->label('Minutos adicionales')->numeric()->minValue(0)->maxValue(59)->default(0)->suffix('min'),
                DateTimePicker::make('cita_cargue')->label('Cita de cargue')->seconds(false)->native(false),
                DateTimePicker::make('cita_descargue')->label('Cita de descargue')->seconds(false)->native(false),
                DateTimePicker::make('llegada_cargue')->label('Llegada real al cargue')->seconds(false)->native(false),
                DateTimePicker::make('entrada_cargue')->label('Entrada real al cargue')->seconds(false)->native(false),
                DateTimePicker::make('salida_cargue')->label('Salida real del cargue')->seconds(false)->native(false)->columnSpanFull(),
                Textarea::make('observaciones')->rows(2)->maxLength(200)->columnSpanFull(),
            ]);
    }
}
