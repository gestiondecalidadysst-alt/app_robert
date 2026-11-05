<?php

namespace App\Filament\App\Resources\Servicios\Pages;

use App\Actions\Operacion\PlanificarServicio;
use App\Filament\App\Pages\Planeador;
use App\Filament\App\Resources\Fuecs\FuecResource;
use App\Filament\App\Resources\Servicios\ServicioResource;
use App\Filament\Compartido\ErroresDeNegocio;
use App\Models\Fuec;
use App\Models\Servicio;
use App\Support\Pdf\PdfPlanViaje;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class VerServicio extends ViewRecord
{
    protected static string $resource = ServicioResource::class;

    public function getTitle(): string
    {
        /** @var Servicio $s */
        $s = $this->getRecord();

        return 'Servicio '.$s->fecha_inicio->format('d/m/Y').' · '.$s->ruta_texto;
    }

    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->load(['vehiculo', 'conductores', 'origen', 'destino', 'ruta', 'fuec', 'contrato.cliente', 'usuario', 'leidoPor']);
    }

    protected function getHeaderActions(): array
    {
        /** @var Servicio $s */
        $s = $this->getRecord();

        return [
            Action::make('generar_extracto')
                ->label('Generar extracto')
                ->icon('heroicon-o-document-plus')
                ->visible(fn () => ! $s->fuec_id && ! $s->cancelado() && $s->fecha_fin->gte(today()) && auth()->user()->can('create', Fuec::class))
                ->url(fn () => FuecResource::getUrl('create', ['servicio' => $s->id])),
            Action::make('ver_extracto')
                ->label('Ver extracto')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->visible(fn () => (bool) $s->fuec_id)
                ->url(fn () => FuecResource::getUrl('view', ['record' => $s->fuec_id])),
            Action::make('plan_pdf')
                ->label('Plan de viaje (PDF)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => response()->streamDownload(fn () => print (PdfPlanViaje::servicio($s->fresh())), PdfPlanViaje::nombre($s), ['Content-Type' => 'application/pdf'])),
            EditAction::make()->label('Editar plan')->visible(fn () => ! $s->cancelado()),
            Action::make('cancelar')
                ->label('Cancelar')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => ! $s->cancelado() && (! $s->fuec_id || $s->fuec?->anulado()))
                ->authorize(fn () => auth()->user()->can('anular', $s))
                ->requiresConfirmation()
                ->schema([Textarea::make('motivo')->label('Motivo')->required()->rows(2)])
                ->action(function (array $data, Action $action) use ($s): void {
                    ErroresDeNegocio::intentar($action, fn () => app(PlanificarServicio::class)->cancelar($s, $data['motivo']));
                    $this->redirect(Planeador::getUrl());
                })
                ->successNotificationTitle('Servicio cancelado'),
        ];
    }
}
