<?php

namespace App\Filament\App\Resources\ManifiestosCarga\Schemas;

use App\Models\Conductor;
use App\Models\ManifiestoCarga;
use App\Models\Municipio;
use App\Models\Propietario;
use App\Models\RemesaCarga;
use App\Models\Remolque;
use App\Models\TerceroCarga;
use App\Models\Vehiculo;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class ManifiestoCargaForm
{
    public static function form(Schema $schema): Schema
    {
        $municipios = fn () => Municipio::query()->with('departamento')->activos()->orderBy('nombre')->limit(50)->get()
            ->mapWithKeys(fn (Municipio $m) => [$m->id => $m->nombre_completo]);

        return $schema->components([
            Section::make('Encabezado')
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    Select::make('tipo')->label('Tipo de manifiesto')->options(ManifiestoCarga::TIPOS)->default('G')->required(),
                    DatePicker::make('fecha_expedicion')->required()->default(today()),
                    Select::make('origen_id')->label('Origen')->required()->searchable()
                        ->getSearchResultsUsing(fn ($s) => Municipio::query()->with('departamento')->activos()->where('nombre', 'like', "%{$s}%")->limit(30)->get()->mapWithKeys(fn ($m) => [$m->id => $m->nombre_completo])->all())
                        ->getOptionLabelUsing(fn ($v) => Municipio::find($v)?->nombre_completo),
                    Select::make('destino_id')->label('Destino')->required()->searchable()
                        ->getSearchResultsUsing(fn ($s) => Municipio::query()->with('departamento')->activos()->where('nombre', 'like', "%{$s}%")->limit(30)->get()->mapWithKeys(fn ($m) => [$m->id => $m->nombre_completo])->all())
                        ->getOptionLabelUsing(fn ($v) => Municipio::find($v)?->nombre_completo),
                ]),
            Section::make('Vehículo y conductor')
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    Select::make('vehiculo_id')->label('Vehículo')->required()->searchable()
                        ->options(fn () => Vehiculo::query()->whereNotNull('rndc_configuracion')->orderBy('placa')->pluck('placa', 'id')),
                    Select::make('remolque_id')->label('Remolque')->searchable()
                        ->options(fn () => Remolque::query()->where('activo', true)->orderBy('placa')->pluck('placa', 'id')),
                    Select::make('conductor_id')->label('Conductor')->required()->searchable()
                        ->options(fn () => Conductor::query()->vinculados()->orderBy('nombres')->get()->mapWithKeys(fn ($c) => [$c->id => $c->nombre_completo . ' · ' . $c->documento])),
                ]),
            Section::make('Titular del manifiesto')
                ->description('Puede ser el propietario del vehículo, el tenedor, el conductor o un tercero (titular en comisión). Llene solo uno.')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Select::make('titular_propietario_id')->label('Titular (propietario o tenedor)')->searchable()
                        ->options(fn () => Propietario::query()->where('activo', true)->orderBy('nombres')->get()->mapWithKeys(fn ($p) => [$p->id => $p->nombre_completo . ' · ' . $p->documento])),
                    Select::make('titular_tercero_id')->label('Titular (tercero)')->searchable()
                        ->options(fn () => TerceroCarga::query()->where('activo', true)->orderBy('nombre')->get()->mapWithKeys(fn ($t) => [$t->id => $t->nombre_completo . ' · ' . $t->documento])),
                ]),
            Section::make('Valores')
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextInput::make('valor_flete')->label('Valor del flete pactado')->numeric()->required()->minValue(1)->prefix('$'),
                    TextInput::make('valor_minimo_sicetac')->label('Valor mínimo SICE-TAC')->numeric()->prefix('$')->helperText('Consúltelo en SICE-TAC. Si se deja vacío no se valida.'),
                    TextInput::make('anticipo')->label('Anticipo')->numeric()->minValue(0)->default(0)->prefix('$'),
                    TextInput::make('retencion_fuente_pct')->label('Retención fuente (%)')->numeric()->minValue(0)->maxValue(20)->step(0.01)->default(1),
                    TextInput::make('reteica_por_mil')->label('Reteica (x1000)')->numeric()->minValue(0)->maxValue(20)->step(0.01)->default(0),
                    Select::make('municipio_pago_id')->label('Municipio de pago del saldo')->searchable()
                        ->getSearchResultsUsing(fn ($s) => Municipio::query()->activos()->with('departamento')->where('nombre', 'like', "%{$s}%")->limit(30)->get()->mapWithKeys(fn ($m) => [$m->id => $m->nombre_completo])->all())
                        ->getOptionLabelUsing(fn ($v) => Municipio::find($v)?->nombre_completo),
                    DatePicker::make('fecha_pago_saldo')->label('Fecha de pago del saldo'),
                    Select::make('responsable_cargue')->label('Responsable pago cargue')->options(ManifiestoCarga::RESPONSABLES_PAGO)->default('E')->required(),
                    Select::make('responsable_descargue')->label('Responsable pago descargue')->options(ManifiestoCarga::RESPONSABLES_PAGO)->default('E')->required(),
                ]),
            Section::make('Remesas asociadas')
                ->schema([
                    Select::make('remesas_ids')->label('Remesas')->multiple()->required()
                        ->options(fn ($record) => RemesaCarga::query()
                            ->where(fn ($q) => $q->whereNull('manifiesto_id')->orWhere('manifiesto_id', $record?->id))
                            ->whereNull('anulado_at')->orderBy('id', 'desc')->get()
                            ->mapWithKeys(fn ($r) => [$r->id => $r->numeroFormateado() . ' · ' . $r->producto_descripcion]))
                        ->default(fn ($record) => $record?->remesas->pluck('id')->all() ?? [])
                        ->dehydrated(false)
                        ->afterStateHydrated(fn ($component, $record) => $component->state($record?->remesas->pluck('id')->all() ?? [])),
                ]),
            Section::make('Observaciones')
                ->schema([Textarea::make('observaciones')->rows(2)->maxLength(200)]),
        ]);
    }
}
