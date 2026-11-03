<?php

namespace App\Filament\App\Resources\ProgramasMantenimiento\Pages;

use App\Actions\Mantenimiento\ProgramaAnual;
use App\Enums\EstadoPrograma;
use App\Filament\App\Resources\ProgramasMantenimiento\ProgramaMantenimientoResource;
use App\Filament\Compartido\ErroresDeNegocio;
use App\Models\ProgramaMantenimiento;
use App\Models\Vehiculo;
use App\Support\Mantenimiento\ExportarPrograma;
use App\Support\Pdf\PdfProgramaMantenimiento;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class VerPrograma extends ViewRecord
{
    protected static string $resource = ProgramaMantenimientoResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Programa de mantenimiento ' . $this->getRecord()->anio . ' · v' . $this->getRecord()->version;
    }

    public function getSubheading(): ?string
    {
        return match ($this->getRecord()->estado) {
            EstadoPrograma::Borrador => 'Borrador: revíselo y apruébelo. Al aprobarlo, las fechas quedan como línea base.',
            EstadoPrograma::Aprobado => 'Aprobado. Las ejecuciones, reprogramaciones y cancelaciones quedan en el historial de cada actividad.',
            EstadoPrograma::Reemplazado => 'Reemplazado por una versión más reciente (solo consulta).',
        };
    }

    protected function getHeaderActions(): array
    {
        /** @var ProgramaMantenimiento $programa */
        $programa = $this->getRecord();

        return [
            Action::make('aprobar')
                ->label('Aprobar programa')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn () => $programa->estado === EstadoPrograma::Borrador && auth()->user()?->can('aprobar', $programa))
                ->requiresConfirmation()
                ->modalDescription(fn () => 'Elaboró: ' . ($programa->elaboradoPor?->name ?? '—') . '. Quedará aprobado por ' . auth()->user()->name
                    . ' y sus fechas serán la línea base para medir el cumplimiento.'
                    . (ProgramaMantenimiento::query()->where('anio', $programa->anio)->where('estado', 'aprobado')->exists() ? ' La versión aprobada anterior quedará reemplazada (lo ya ejecutado pasa a esta).' : ''))
                ->action(function (Action $action) use ($programa): void {
                    ErroresDeNegocio::intentar($action, fn () => ProgramaAnual::aprobar($programa, auth()->user()));
                    Notification::make()->success()->title('Programa aprobado')->send();
                    $this->refreshFormData([]);
                    $this->redirect(ProgramaMantenimientoResource::getUrl('view', ['record' => $programa]));
                }),
            Action::make('regenerar')
                ->label('Regenerar')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn () => $programa->estado === EstadoPrograma::Borrador && auth()->user()?->can('crear_programas_mantenimiento'))
                ->requiresConfirmation()
                ->modalDescription('Vuelve a calcular todas las actividades con los planes y kilometrajes actuales.')
                ->action(function (Action $action) use ($programa): void {
                    ErroresDeNegocio::intentar($action, fn () => ProgramaAnual::generar(Filament::getTenant(), $programa->anio, [], auth()->user()));
                    Notification::make()->success()->title('Programa regenerado')->send();
                    $this->redirect(ProgramaMantenimientoResource::getUrl('view', ['record' => $programa]));
                }),
            Action::make('incorporar')
                ->label('Incorporar vehículo')
                ->icon('heroicon-o-plus')
                ->color('gray')
                ->visible(fn () => $programa->estado !== EstadoPrograma::Reemplazado && auth()->user()?->can('editar_programas_mantenimiento'))
                ->schema([
                    Select::make('vehiculo_id')->label('Vehículo')->required()->searchable()
                        ->options(fn () => Vehiculo::query()->enFlota()
                            ->whereNotIn('id', $programa->actividades()->pendientes()->select('vehiculo_id'))
                            ->orderBy('placa')->pluck('placa', 'id')),
                ])
                ->modalDescription('Para vehículos vinculados después de generar el programa. Sus actividades quedan marcadas como incorporadas.')
                ->action(function (array $data, Action $action) use ($programa): void {
                    $n = ErroresDeNegocio::intentar($action, fn () => ProgramaAnual::incorporarVehiculo($programa, Vehiculo::query()->findOrFail($data['vehiculo_id']), auth()->user()));
                    Notification::make()->success()->title("{$n} actividad(es) agregadas")->send();
                }),
            ActionGroup::make([
                Action::make('pdf')
                    ->label('Cronograma anual (PDF)')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Select::make('vehiculo_id')->label('Vehículo')->placeholder('Toda la flota')->searchable()
                            ->options(fn () => Vehiculo::query()->whereIn('id', $programa->actividades()->select('vehiculo_id'))->orderBy('placa')->pluck('placa', 'id')),
                    ])
                    ->action(function (array $data) use ($programa) {
                        $vehiculo = filled($data['vehiculo_id'] ?? null) ? Vehiculo::query()->find($data['vehiculo_id']) : null;

                        return response()->streamDownload(fn () => print (PdfProgramaMantenimiento::generar($programa, $vehiculo)),
                            "Programa-mantenimiento-{$programa->anio}-v{$programa->version}" . ($vehiculo ? "-{$vehiculo->placa}" : '') . '.pdf',
                            ['Content-Type' => 'application/pdf']);
                    }),
                Action::make('excel')
                    ->label('Exportar para la Supertransporte (Excel)')
                    ->icon('heroicon-o-table-cells')
                    ->action(fn () => response()->download(ExportarPrograma::excel($programa),
                        "Mantenimiento-PESV-{$programa->anio}-v{$programa->version}.xlsx")->deleteFileAfterSend()),
            ])->label('Descargar')->icon('heroicon-o-arrow-down-tray')->button()->color('gray'),
        ];
    }
}
