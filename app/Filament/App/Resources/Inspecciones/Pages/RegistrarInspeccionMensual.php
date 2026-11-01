<?php

namespace App\Filament\App\Resources\Inspecciones\Pages;

use App\Actions\Pesv\RegistrarInspeccion;
use App\Enums\TipoInspeccion;
use App\Filament\App\Resources\Inspecciones\InspeccionResource;
use App\Models\Conductor;
use App\Models\ItemChequeo;
use App\Models\Vehiculo;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Inspección mensual del vehículo, registrada por la empresa (ítems de Seguridad vial → Ítems de chequeo).
 */
class RegistrarInspeccionMensual extends Page
{
    protected static string $resource = InspeccionResource::class;

    protected string $view = 'filament.inspecciones.mensual';

    protected static ?string $title = 'Registrar inspección mensual';

    public ?array $data = [];

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->can('crear_inspecciones') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(['fecha' => today()->toDateString()]);
    }

    public function form(Schema $schema): Schema
    {
        $items = ItemChequeo::query()->deTipo(TipoInspeccion::Mensual)->activos()->ordenados()->get();

        return $schema
            ->statePath('data')
            ->components([
                Section::make()
                    ->columns(['default' => 1, 'md' => 4])
                    ->schema([
                        Select::make('vehiculo_id')->label('Vehículo')->required()->searchable()
                            ->options(fn () => Vehiculo::query()->enFlota()->orderBy('placa')->pluck('placa', 'id')),
                        Select::make('conductor_id')->label('Conductor (opcional)')->searchable()
                            ->options(fn () => Conductor::query()->vinculados()->orderBy('nombres')->get()->pluck('nombre_completo', 'id')),
                        DatePicker::make('fecha')->required()->maxDate(today()),
                        TextInput::make('kilometraje')->numeric()->minValue(0)->maxValue(9999999),
                    ]),
                ...$items->groupBy('grupo')->map(fn ($grupo, $nombre) => Section::make($nombre)
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema($grupo->flatMap(fn (ItemChequeo $item) => [
                        ToggleButtons::make("respuestas.{$item->id}.respuesta")
                            ->label($item->descripcion . ($item->critico ? ' (crítico)' : ''))
                            ->options(RegistrarInspeccion::RESPUESTAS)
                            ->colors(['si' => 'success', 'no' => 'danger', 'na' => 'gray'])
                            ->inline()
                            ->required()
                            ->live(),
                        Textarea::make("respuestas.{$item->id}.observacion")
                            ->label('Observación')
                            ->rows(1)
                            ->required(fn (Get $get) => $get("respuestas.{$item->id}.respuesta") === 'no')
                            ->visible(fn (Get $get) => $get("respuestas.{$item->id}.respuesta") === 'no'),
                    ])->all()))->values()->all(),
                Section::make()->schema([
                    Textarea::make('observaciones')->label('Observaciones generales')->rows(3),
                ]),
            ]);
    }

    public function guardar(RegistrarInspeccion $registrar): void
    {
        $datos = $this->form->getState();
        $empresa = Filament::getTenant();

        try {
            $inspeccion = $registrar(
                $empresa,
                TipoInspeccion::Mensual,
                Vehiculo::query()->findOrFail($datos['vehiculo_id']),
                $datos['respuestas'] ?? [],
                conductor: filled($datos['conductor_id'] ?? null) ? Conductor::query()->find($datos['conductor_id']) : null,
                kilometraje: filled($datos['kilometraje'] ?? null) ? (int) $datos['kilometraje'] : null,
                observaciones: $datos['observaciones'] ?? null,
                contexto: ['ip' => request()->ip(), 'user_agent' => request()->userAgent()],
                usuario: auth()->user(),
                fecha: \Illuminate\Support\Carbon::parse($datos['fecha']),
            );
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($m, $campo) => [
                'data.' . (str_starts_with($campo, 'respuestas') ? $campo : ($campo === 'vehiculo' ? 'vehiculo_id' : $campo)) => $m,
            ])->all());
        }

        Notification::make()->success()->title('Inspección registrada: ' . $inspeccion->resultado->getLabel())->send();

        $this->redirect(InspeccionResource::getUrl('view', ['record' => $inspeccion]));
    }
}
