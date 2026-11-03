<?php

namespace App\Filament\App\Resources\ProgramasMantenimiento\Pages;

use App\Actions\Mantenimiento\ProgramaAnual;
use App\Filament\App\Resources\ProgramasMantenimiento\ProgramaMantenimientoResource;
use App\Filament\Compartido\ErroresDeNegocio;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;

class ListarProgramas extends ListRecords
{
    protected static string $resource = ProgramaMantenimientoResource::class;

    protected static ?string $title = 'Programa anual de mantenimiento';

    public function getSubheading(): ?string
    {
        return 'Se genera desde el plan de cada vehículo y su punto de partida; se aprueba como línea base y cada cambio queda trazado.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generar')
                ->label('Generar programa')
                ->icon('heroicon-o-sparkles')
                ->visible(fn () => auth()->user()?->can('crear_programas_mantenimiento'))
                ->schema([
                    Select::make('anio')->label('Año')->required()
                        ->options([today()->year => today()->year, today()->year + 1 => today()->year + 1])
                        ->default(today()->month >= 11 ? today()->year + 1 : today()->year),
                    TextInput::make('km_diarios_defecto')->label('Km/día por defecto')->numeric()->minValue(1)->maxValue(2000)->required()
                        ->default(fn () => Filament::getTenant()->km_diarios_defecto ?? 150)
                        ->helperText('Para vehículos sin lecturas de kilometraje suficientes (los demás usan su promedio real).'),
                    TextInput::make('codigo_formato')->label('Código del formato (sistema de gestión)')->maxLength(40)->placeholder('Ej.: GMT-PR02-FT01'),
                    Textarea::make('observaciones')->rows(2),
                ])
                ->modalDescription('Si ya hay un borrador de ese año, se regenera. Si hay uno aprobado, se crea una versión nueva en borrador.')
                ->action(function (array $data, Action $action): void {
                    $programa = ErroresDeNegocio::intentar($action, fn () => ProgramaAnual::generar(Filament::getTenant(), (int) $data['anio'], $data, auth()->user()));
                    $this->redirect(ProgramaMantenimientoResource::getUrl('view', ['record' => $programa]));
                }),
        ];
    }
}
