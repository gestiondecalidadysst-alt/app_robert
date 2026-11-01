<?php

namespace App\Filament\App\Resources\PlanesAccion\Pages;

use App\Actions\Mantenimiento\GestionarPlanAccion;
use App\Enums\EstadoPlan;
use App\Filament\App\Resources\PlanesAccion\PlanAccionResource;
use App\Models\PlanAccion;
use App\Models\Vehiculo;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListarPlanesAccion extends ListRecords
{
    protected static ?string $title = 'Planes de acción';

    protected static string $resource = PlanAccionResource::class;

    public function getSubheading(): ?string
    {
        $vencidos = PlanAccion::query()->pendientes()->whereDate('fecha_limite', '<', today())->count();

        return 'Pendientes: ' . PlanAccion::query()->pendientes()->count() . ' · vencidos: ' . $vencidos
            . ' · por verificar: ' . PlanAccion::query()->where('estado', EstadoPlan::PorVerificar->value)->count();
    }

    public function getTabs(): array
    {
        return [
            'pendientes' => Tab::make('Pendientes')->modifyQueryUsing(fn (Builder $query) => $query->pendientes()),
            'verificar' => Tab::make('Por verificar')
                ->badge(fn () => PlanAccion::query()->where('estado', EstadoPlan::PorVerificar->value)->count() ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('estado', EstadoPlan::PorVerificar->value)),
            'cerrados' => Tab::make('Cerrados')->modifyQueryUsing(fn (Builder $query) => $query->where('estado', EstadoPlan::Cerrado->value)),
            'todos' => Tab::make('Todos'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('nuevo')
                ->label('Nuevo plan de acción')
                ->icon('heroicon-o-plus')
                ->visible(fn () => auth()->user()?->can('crear_planes_accion'))
                ->schema(PlanAccionResource::camposManual())
                ->modalDescription('Para hallazgos fuera del preoperacional (controles, quejas, incidentes). Los de preoperacionales, inspecciones y rutinas vencidas se abren solos.')
                ->action(function (array $data): void {
                    $plan = GestionarPlanAccion::crear(Filament::getTenant(), Vehiculo::query()->findOrFail($data['vehiculo_id']), $data, auth()->user());
                    $this->redirect(PlanAccionResource::getUrl('view', ['record' => $plan]));
                }),
        ];
    }
}
