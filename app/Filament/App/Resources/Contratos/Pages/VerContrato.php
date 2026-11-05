<?php

namespace App\Filament\App\Resources\Contratos\Pages;

use App\Actions\Operacion\AnularContrato;
use App\Actions\Operacion\EnviarRecibo;
use App\Filament\App\Resources\Contratos\ContratoResource;
use App\Filament\App\Resources\Fuecs\FuecResource;
use App\Filament\Compartido\ErroresDeNegocio;
use App\Models\Contrato;
use App\Models\Fuec;
use App\Models\PagoContrato;
use App\Support\Pdf\PdfRecibo;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;

class VerContrato extends ViewRecord
{
    protected static string $resource = ContratoResource::class;

    public function getTitle(): string
    {
        return "Contrato {$this->getRecord()->numero_formateado}";
    }

    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->load(['cliente.tipoIdentificacion', 'origen.departamento', 'destino.departamento', 'usuario', 'oficina']);
    }

    protected function getHeaderActions(): array
    {
        /** @var Contrato $contrato */
        $contrato = $this->getRecord();

        return [
            Action::make('expedir')
                ->label('Expedir FUEC')
                ->icon('heroicon-o-document-plus')
                ->visible(fn () => ! $contrato->anulado() && $contrato->fecha_fin->gte(today()) && auth()->user()->can('create', Fuec::class))
                ->url(fn () => FuecResource::getUrl('create', ['contrato' => $contrato->getKey()])),
            ActionGroup::make([
                Action::make('estado_cuenta')
                    ->label(fn () => $contrato->pagado() ? 'Descargar paz y salvo' : 'Descargar estado de cuenta')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn () => response()->streamDownload(
                        fn () => print (PdfRecibo::estadoCuenta($contrato->fresh())),
                        PdfRecibo::nombreEstadoCuenta($contrato),
                        ['Content-Type' => 'application/pdf'],
                    )),
                Action::make('enviar_estado_cuenta')
                    ->label(fn () => $contrato->pagado() ? 'Enviar paz y salvo al cliente' : 'Enviar estado de cuenta al cliente')
                    ->icon('heroicon-o-paper-airplane')
                    ->authorize(fn () => auth()->user()->can('create', PagoContrato::class))
                    ->modalSubmitActionLabel('Enviar')
                    ->schema([TextInput::make('email')->label('Correo del cliente')->email()->required()->default(fn () => EnviarRecibo::contacto($contrato)['email'])])
                    ->action(function (array $data, Action $action) use ($contrato): void {
                        $canales = ErroresDeNegocio::intentar($action, fn () => app(EnviarRecibo::class)->estadoCuenta($contrato->fresh(), $data['email']));
                        Notification::make()->success()->title('Enviado por '.implode(' y ', $canales))->send();
                    }),
            ])
                ->label(fn () => $contrato->pagado() ? 'Paz y salvo' : 'Estado de cuenta')
                ->icon('heroicon-o-receipt-percent')
                ->color(fn () => $contrato->pagado() ? 'success' : 'gray')
                ->button()
                ->visible(fn () => auth()->user()->can('viewAny', PagoContrato::class) && (float) $contrato->valor > 0),
            EditAction::make()->visible(fn () => ! $contrato->anulado() && ! $contrato->documentoActivo()),
            Action::make('anular')
                ->label('Anular')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => ! $contrato->anulado())
                ->authorize(fn () => auth()->user()->can('anular', $contrato))
                ->requiresConfirmation()
                ->modalDescription('El contrato queda anulado y no permite expedir FUEC. Primero deben anularse sus FUEC.')
                ->schema([Textarea::make('motivo')->label('Motivo')->required()->rows(2)])
                ->action(function (array $data, Action $action) use ($contrato): void {
                    ErroresDeNegocio::intentar($action, fn () => app(AnularContrato::class)($contrato, $data['motivo']));
                    $this->refrescar();
                })
                ->successNotificationTitle('Contrato anulado'),
        ];
    }

    #[On('contrato-actualizado')]
    public function refrescar(): void
    {
        $this->getRecord()->refresh();
    }

    public function getContentTabLabel(): ?string
    {
        return 'Contrato';
    }
}
