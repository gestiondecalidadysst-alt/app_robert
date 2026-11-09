<?php

namespace App\Filament\App\Resources\Contratos\RelationManagers;

use App\Actions\Operacion\AnularPago;
use App\Actions\Operacion\EnviarRecibo;
use App\Actions\Operacion\RegistrarPago;
use App\Enums\MetodoPago;
use App\Filament\Compartido\ErroresDeNegocio;
use App\Models\Contrato;
use App\Models\PagoContrato;
use App\Support\Pdf\PdfRecibo;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Pagos del contrato con su recibo de caja: registrar (con envío opcional al cliente), descargar el
 * recibo, enviarlo y anular. Saldo = valor − pagos no anulados.
 */
class PagosRelationManager extends RelationManager
{
    protected static string $relationship = 'pagos';

    protected static ?string $title = 'Pagos y recibos';

    protected static ?string $modelLabel = 'pago';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-banknotes';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()->can('viewAny', PagoContrato::class);
    }

    public static function pesos($valor): string
    {
        return '$ '.number_format((float) $valor, 0, ',', '.');
    }

    /** Campos de envío al cliente (correo y celular), con los datos del cliente sugeridos. */
    private function camposEnvio(bool $conCelular = true, ?string $visibleSi = null): array
    {
        $contacto = EnviarRecibo::contacto($this->getOwnerRecord());
        $visible = fn (Get $get) => $visibleSi === null || (bool) $get($visibleSi);

        return [
            Grid::make(['default' => 1, 'md' => $conCelular ? 2 : 1])->schema(array_filter([
                TextInput::make('email')->label('Correo del cliente')->email()->default($contacto['email'])->visible($visible),
                $conCelular ? TextInput::make('celular')->label('Celular (SMS con el enlace)')->tel()->default($contacto['celular'])->visible($visible) : null,
            ]))->columnSpanFull(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['usuario', 'contrato']))
            ->defaultSort(fn ($query) => $query->orderByDesc('fecha')->orderByDesc('id'))
            ->emptyStateHeading('Sin pagos')
            ->emptyStateDescription('Cada pago genera su recibo de caja para entregar al cliente.')
            ->recordClasses(fn (PagoContrato $record) => $record->anulado() ? 'opacity-60' : null)
            ->columns([
                TextColumn::make('numero')->label('Recibo')->weight('bold')
                    ->formatStateUsing(fn (PagoContrato $record) => $record->numero_recibo)
                    ->description(fn (PagoContrato $record) => $record->anulado() ? 'Anulado' : null),
                TextColumn::make('fecha')->date('d/m/Y'),
                TextColumn::make('tipo')->label('Tipo')->badge()
                    ->state(fn (PagoContrato $record) => $record->anulado() ? 'Anulado' : $record->tipoEtiqueta())
                    ->color(fn (string $state) => match ($state) {
                        'Anulado' => 'danger',
                        'Abono' => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('valor')->formatStateUsing(fn ($state) => self::pesos($state))->alignEnd(),
                TextColumn::make('saldo_despues')->label('Saldo después')->alignEnd()->visibleFrom('md')
                    ->formatStateUsing(fn ($state, PagoContrato $record) => $record->anulado() ? '—' : self::pesos($state)),
                TextColumn::make('metodo')->label('Método')->badge()->color('gray')->visibleFrom('md'),
                TextColumn::make('referencia')->placeholder('—')->visibleFrom('lg'),
                TextColumn::make('usuario.name')->label('Registró')->placeholder('—')->visibleFrom('lg'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Registrar pago')
                    ->modalHeading(fn () => 'Registrar pago · saldo '.self::pesos($this->getOwnerRecord()->saldo()))
                    ->modalDescription('Al guardar se genera el recibo de caja con su número consecutivo.')
                    ->createAnother(false)
                    ->modalSubmitActionLabel('Registrar y generar recibo')
                    ->visible(fn () => ! $this->getOwnerRecord()->anulado() && $this->getOwnerRecord()->saldo() > 0)
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            DatePicker::make('fecha')->default(now())->maxDate(now())->required(),
                            TextInput::make('valor')->numeric()->prefix('$')->minValue(1)->required()
                                ->default(fn () => $this->getOwnerRecord()->saldo())
                                ->helperText(fn () => 'Con '.self::pesos($this->getOwnerRecord()->saldo()).' queda pagado en su totalidad; con menos, es un abono.'),
                            Select::make('metodo')->label('Método')->options(MetodoPago::class)->default(MetodoPago::Transferencia->value)->required(),
                            TextInput::make('referencia')->maxLength(255)->placeholder('No. de transferencia, voucher…'),
                        ]),
                        Textarea::make('observacion')->label('Observación')->rows(2),
                        Toggle::make('enviar')->label('Enviar el recibo al cliente')->live()
                            ->default(fn () => filled(EnviarRecibo::contacto($this->getOwnerRecord())['email'])),
                        ...$this->camposEnvio(visibleSi: 'enviar'),
                    ])
                    ->using(fn (array $data, CreateAction $action) => ErroresDeNegocio::intentar(
                        $action,
                        fn () => app(RegistrarPago::class)($this->getOwnerRecord(), collect($data)->except(['enviar', 'email', 'celular'])->all()),
                    ))
                    ->after(function (PagoContrato $record, array $data): void {
                        $this->dispatch('contrato-actualizado');
                        $this->avisarRecibo($record, $data);
                    })
                    ->successNotification(null),
            ])
            ->recordActions([
                Action::make('recibo')->iconButton()
                    ->label('Recibo')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn (PagoContrato $record) => response()->streamDownload(
                        fn () => print (PdfRecibo::recibo($record)),
                        PdfRecibo::nombreRecibo($record),
                        ['Content-Type' => 'application/pdf'],
                    )),
                ActionGroup::make([
                    Action::make('enviar')
                        ->label('Enviar al cliente')
                        ->icon('heroicon-o-paper-airplane')
                        ->visible(fn (PagoContrato $record) => ! $record->anulado())
                        ->authorize(fn () => auth()->user()->can('create', PagoContrato::class))
                        ->modalHeading(fn (PagoContrato $record) => "Enviar recibo {$record->numero_recibo}")
                        ->modalDescription('Por correo va el PDF adjunto; por SMS, un enlace para verlo y descargarlo.')
                        ->modalSubmitActionLabel('Enviar')
                        ->schema(fn () => $this->camposEnvio())
                        ->action(function (PagoContrato $record, array $data, Action $action): void {
                            $canales = ErroresDeNegocio::intentar($action, fn () => app(EnviarRecibo::class)->recibo($record, $data['email'] ?? null, $data['celular'] ?? null));
                            Notification::make()->success()->title('Recibo enviado')->body('Por '.implode(' y ', $canales).'.')->send();
                        }),
                    Action::make('anular')
                        ->label('Anular')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (PagoContrato $record) => ! $record->anulado())
                        ->authorize(fn (PagoContrato $record) => auth()->user()->can('anular', $record))
                        ->requiresConfirmation()
                        ->modalHeading(fn (PagoContrato $record) => "Anular el pago del recibo {$record->numero_recibo}")
                        ->modalDescription('El pago deja de sumar al contrato. El recibo conserva su número y queda marcado como ANULADO.')
                        ->schema([Textarea::make('motivo')->label('Motivo')->required()->rows(2)])
                        ->action(function (PagoContrato $record, array $data, Action $action): void {
                            ErroresDeNegocio::intentar($action, fn () => app(AnularPago::class)($record, $data['motivo']));
                            $this->dispatch('contrato-actualizado');
                        })
                        ->successNotificationTitle('Pago anulado'),
                ]),
            ]);
    }

    private function avisarRecibo(PagoContrato $pago, array $data): void
    {
        $aviso = Notification::make()->success()
            ->title("Pago registrado · recibo {$pago->numero_recibo}")
            ->body($pago->tipo() === PagoContrato::ABONO
                ? 'Abono de '.self::pesos($pago->valor).'. Saldo pendiente: '.self::pesos($pago->saldo_despues).'.'
                : 'El contrato quedó pagado en su totalidad.')
            ->persistent()
            ->actions([
                Action::make('descargar')->label('Ver recibo')->button()->url($pago->urlPdf(), shouldOpenInNewTab: true),
            ]);

        if (! empty($data['enviar'])) {
            try {
                $canales = app(EnviarRecibo::class)->recibo($pago, $data['email'] ?? null, $data['celular'] ?? null);
                $aviso->body($aviso->getBody().' Enviado por '.implode(' y ', $canales).'.');
            } catch (ValidationException $e) {
                $aviso->warning()->body($aviso->getBody().' No se envió al cliente: '.collect($e->errors())->flatten()->first());
            }
        }

        $aviso->send();
    }
}
