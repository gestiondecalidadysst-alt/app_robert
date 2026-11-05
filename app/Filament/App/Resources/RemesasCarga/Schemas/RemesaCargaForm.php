<?php

namespace App\Filament\App\Resources\RemesasCarga\Schemas;

use App\Models\RemesaCarga;
use App\Models\TerceroCarga;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

final class RemesaCargaForm
{
    public static function form(Schema $schema): Schema
    {
        $terceros = fn () => TerceroCarga::query()->where('activo', true)->orderBy('nombre')->get()
            ->mapWithKeys(fn (TerceroCarga $t) => [$t->id => $t->nombre_completo . ' · ' . ($t->tipoIdentificacion?->codigo ?? '') . ' ' . $t->documento]);

        return $schema->components([
            Section::make('Carga')
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    DatePicker::make('fecha')->required()->default(today()),
                    Select::make('operacion')->label('Tipo de operación')->options(RemesaCarga::OPERACIONES)->default('G')->required()->live(),
                    Select::make('naturaleza')->label('Naturaleza de la carga')->options(RemesaCarga::NATURALEZAS)->default(1)->required(),
                    TextInput::make('empaque_codigo')->label('Código del empaque (RNDC)')->required()->maxLength(2)->helperText('Tabla «Empaques» del anexo.'),
                    TextInput::make('producto_codigo')->label('Código del producto (RNDC)')->required()->maxLength(6)->helperText('Tabla «Codificación de Productos». Contenedor vacío = 009990, paqueteo = 009880.'),
                    TextInput::make('producto_descripcion')->label('Descripción corta')->required()->maxLength(60),
                    TextInput::make('cantidad')->label('Cantidad cargada')->numeric()->minValue(1)->maxValue(999999)->hidden(fn (Get $get) => $get('operacion') === 'V')->required(fn (Get $get) => $get('operacion') !== 'V'),
                    Select::make('unidad')->label('Unidad')->options([1 => 'Kilogramos', 2 => 'Galones'])->default(1)->hidden(fn (Get $get) => $get('operacion') === 'V'),
                    TextInput::make('peso_contenedor_vacio')->label('Peso del contenedor vacío (kg)')->numeric()->visible(fn (Get $get) => in_array($get('operacion'), ['C', 'V'])),
                ]),
            Section::make('Remitente y destinatario')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Select::make('remitente_id')->label('Remitente')->required()->searchable()->options($terceros),
                    Select::make('destinatario_id')->label('Destinatario')->required()->searchable()->options($terceros),
                ]),
            Section::make('Póliza de la carga')
                ->columns(['default' => 1, 'md' => 4])
                ->schema([
                    Select::make('dueno_poliza')->label('Tomador de la póliza')->options(RemesaCarga::DUENOS_POLIZA)->default('N')->required()->live(),
                    TextInput::make('numero_poliza')->maxLength(20)->required(fn (Get $get) => $get('dueno_poliza') !== 'N')->visible(fn (Get $get) => $get('dueno_poliza') !== 'N'),
                    DatePicker::make('vence_poliza')->label('Vencimiento')->required(fn (Get $get) => $get('dueno_poliza') !== 'N')->visible(fn (Get $get) => $get('dueno_poliza') !== 'N'),
                    TextInput::make('aseguradora_nit')->label('NIT aseguradora')->maxLength(11)->required(fn (Get $get) => $get('dueno_poliza') !== 'N')->visible(fn (Get $get) => $get('dueno_poliza') !== 'N'),
                ]),
            Section::make('Tiempos pactados y reales de cargue')
                ->columns(['default' => 1, 'md' => 4])
                ->schema([
                    TextInput::make('horas_pacto_cargue')->label('Horas pactadas cargue')->numeric()->minValue(0)->maxValue(999)->default(12),
                    TextInput::make('minutos_pacto_cargue')->numeric()->minValue(0)->maxValue(59)->default(0),
                    TextInput::make('horas_pacto_descargue')->label('Horas pactadas descargue')->numeric()->minValue(0)->maxValue(999)->default(12),
                    TextInput::make('minutos_pacto_descargue')->numeric()->minValue(0)->maxValue(59)->default(0),
                    DateTimePicker::make('cita_cargue')->label('Cita de cargue')->seconds(false),
                    DateTimePicker::make('cita_descargue')->label('Cita de descargue')->seconds(false),
                    DateTimePicker::make('llegada_cargue')->seconds(false),
                    DateTimePicker::make('entrada_cargue')->seconds(false),
                    DateTimePicker::make('salida_cargue')->seconds(false),
                ]),
            Section::make('Observaciones')
                ->schema([Textarea::make('observaciones')->rows(2)->maxLength(200)]),
        ]);
    }
}
