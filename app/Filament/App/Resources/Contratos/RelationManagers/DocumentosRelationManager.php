<?php

namespace App\Filament\App\Resources\Contratos\RelationManagers;

use App\Actions\Contratos\AnularDocumentoContrato;
use App\Actions\Contratos\EmitirDocumentoContrato;
use App\Actions\Contratos\EnviarParaFirma;
use App\Filament\Compartido\ErroresDeNegocio;
use App\Models\Contrato;
use App\Models\DocumentoContrato;
use App\Models\Empresa;
use App\Models\EnvioMensaje;
use App\Models\EventoDocumentoContrato;
use App\Services\Mensajeria\Mensajero;
use App\Support\Contacto;
use App\Support\Pdf\PdfContrato;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * Documento del contrato y su firma electrónica: vista previa, emitir, enviar para firma,
 * reenviar, copiar enlace, historial de evidencias y mensajes, descargar y anular.
 */
class DocumentosRelationManager extends RelationManager
{
    protected static string $relationship = 'documentos';

    protected static ?string $title = 'Documento y firma';

    protected static ?string $modelLabel = 'documento';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-pencil-square';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()->can('viewAny', DocumentoContrato::class);
    }

    private function contrato(): Contrato
    {
        return $this->getOwnerRecord();
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('usuario'))
            ->defaultSort('id', 'desc')
            ->paginated(false)
            ->emptyStateHeading('Sin documento emitido')
            ->emptyStateDescription('Revise la vista previa y emita el contrato para enviarlo a firma.')
            ->columns([
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->state(fn (DocumentoContrato $r) => $r->estadoFirma())
                    ->description(fn (DocumentoContrato $r) => match (true) {
                        $r->firmado() => 'Firmó '.$r->firmante_nombre.' el '.$r->firmado_at->format('d/m/Y h:i a'),
                        $r->anulado() => $r->motivo_anulacion,
                        $r->token_expira_at !== null => 'Enlace vence el '.$r->token_expira_at->format('d/m/Y'),
                        default => null,
                    }),
                TextColumn::make('version_plantilla')->label('Plantilla')
                    ->formatStateUsing(fn (DocumentoContrato $r) => ($r->codigo_formato ? $r->codigo_formato.' · ' : '').'v'.str_pad((string) $r->version_plantilla, 2, '0', STR_PAD_LEFT))
                    ->visibleFrom('md'),
                TextColumn::make('firmante_nombre')->label('Firmante')->placeholder('—')
                    ->description(fn (DocumentoContrato $r) => collect([
                        $r->firmante_celular ? Contacto::ocultarCelular($r->firmante_celular) : null,
                        $r->firmante_email ? Contacto::ocultarCorreo($r->firmante_email) : null,
                    ])->filter()->implode(' · ') ?: null),
                TextColumn::make('created_at')->label('Emitido')->dateTime('d/m/Y h:i a')
                    ->description(fn (DocumentoContrato $r) => $r->usuario?->name)->visibleFrom('lg'),
            ])
            ->headerActions([
                $this->accionVistaPrevia(),
                $this->accionEmitir(),
            ])
            ->recordActions([
                $this->accionEnviar(),
                ActionGroup::make([
                    $this->accionPdf(),
                    $this->accionCopiarEnlace(),
                    $this->accionHistorial(),
                    $this->accionAnular(),
                ]),
            ]);
    }

    private function accionVistaPrevia(): Action
    {
        return Action::make('vistaPrevia')
            ->label('Vista previa')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->visible(fn () => ! $this->contrato()->anulado() && ! $this->contrato()->documentoActivo())
            ->action(function (Action $action) {
                /** @var Empresa $empresa */
                $empresa = Filament::getTenant();
                $plantilla = $empresa->plantillaContratoVigente();

                if (! $plantilla) {
                    Notification::make()->title('No hay plantilla de contrato')
                        ->body('Créela en Configuración → Plantilla de contrato.')->danger()->send();
                    $action->halt();
                }

                return response()->streamDownload(
                    fn () => print (PdfContrato::vistaPrevia($this->contrato(), $plantilla, $empresa)),
                    'Vista-previa-contrato-'.$this->contrato()->numero_formateado.'.pdf',
                    ['Content-Type' => 'application/pdf'],
                );
            });
    }

    private function accionEmitir(): Action
    {
        return Action::make('emitir')
            ->label('Emitir contrato')
            ->icon('heroicon-o-document-check')
            ->visible(fn () => ! $this->contrato()->anulado() && ! $this->contrato()->documentoActivo())
            ->authorize(fn () => auth()->user()->can('create', DocumentoContrato::class))
            ->modalHeading('Emitir el contrato')
            ->modalDescription(function () {
                $faltantes = app(EmitirDocumentoContrato::class)->revisar($this->contrato(), Filament::getTenant());

                return $faltantes === []
                    ? 'Se congela el texto con la plantilla vigente y los datos actuales. Después no se puede editar: si hay que cambiar algo, se anula y se emite de nuevo.'
                    : new HtmlString('<strong>No se puede emitir todavía. Falta:</strong><br>'.collect($faltantes)->map(fn ($f) => '• '.e($f))->implode('<br>'));
            })
            ->modalSubmitAction(fn (Action $action) => app(EmitirDocumentoContrato::class)->revisar($this->contrato(), Filament::getTenant()) === []
                ? $action->label('Emitir')
                : false)
            ->action(function (Action $action) {
                $documento = ErroresDeNegocio::intentar($action, fn () => app(EmitirDocumentoContrato::class)($this->contrato()));
                $this->dispatch('contrato-actualizado');

                Notification::make()
                    ->success()
                    ->title('Contrato emitido')
                    ->body($documento?->enviado_at ? 'Se envió automáticamente para firma.' : 'Ahora puede enviarlo para firma.')
                    ->send();
            });
    }

    private function accionEnviar(): Action
    {
        return Action::make('enviar')
            ->label(fn (DocumentoContrato $r) => $r->enviado_at ? 'Reenviar' : 'Enviar para firma')
            ->icon('heroicon-o-paper-airplane')
            ->button()
            ->visible(fn (DocumentoContrato $r) => ! $r->anulado() && ! $r->firmado())
            ->authorize(fn () => auth()->user()->can('create', DocumentoContrato::class))
            ->modalHeading('Enviar para firma')
            ->modalDescription(function () {
                $motivo = Mensajero::motivoSinSms(Filament::getTenant());

                return 'Se enviará el enlace del contrato por SMS (si hay celular) y por correo (si hay correo). Revise que los datos sean correctos.'
                    .($motivo ? " Nota: {$motivo}" : '');
            })
            ->fillForm(fn (DocumentoContrato $r) => $r->firmante_nombre
                ? ['nombre' => $r->firmante_nombre, 'documento' => $r->firmante_documento, 'celular' => $r->firmante_celular, 'email' => $r->firmante_email]
                : EnviarParaFirma::firmantePorDefecto($this->contrato()))
            ->schema([
                TextInput::make('nombre')->label('Nombre de quien firma')->required()->maxLength(255),
                TextInput::make('documento')->label('Documento de quien firma')->maxLength(30),
                TextInput::make('celular')->label('Celular')->tel()->maxLength(20)->helperText('10 dígitos, empieza por 3. A este número llegan el enlace y el código.'),
                TextInput::make('email')->label('Correo')->email()->maxLength(255),
            ])
            ->modalSubmitActionLabel('Enviar')
            ->action(function (DocumentoContrato $record, array $data, Action $action) {
                $resultado = ErroresDeNegocio::intentar($action, fn () => app(EnviarParaFirma::class)($record, $data));
                $this->dispatch('contrato-actualizado');

                $notificacion = Notification::make()->title('Enlace de firma enviado');

                if ($resultado['canales'] === []) {
                    $notificacion->warning()->title('No se envió ningún mensaje')
                        ->body(implode(' ', $resultado['omitidos']).' Use «Copiar enlace» para compartirlo.')->persistent();
                } elseif ($resultado['omitidos']) {
                    $notificacion->warning()->body('Por '.implode(' y ', $resultado['canales']).'. '.implode(' ', $resultado['omitidos']))->persistent();
                } else {
                    $notificacion->success()->body('Por '.implode(' y ', $resultado['canales']).'.');
                }

                $notificacion->send();
            });
    }

    private function accionPdf(): Action
    {
        return Action::make('pdf')
            ->label(fn (DocumentoContrato $r) => $r->firmado() ? 'Descargar contrato firmado' : 'Descargar PDF')
            ->icon('heroicon-o-arrow-down-tray')
            ->action(function (DocumentoContrato $record) {
                if ($record->firmado() && $record->pdf_firmado_path) {
                    return Storage::disk(DocumentoContrato::DISCO)->download($record->pdf_firmado_path, $record->nombreArchivo());
                }

                return response()->streamDownload(fn () => print (PdfContrato::documento($record)), $record->nombreArchivo(), ['Content-Type' => 'application/pdf']);
            });
    }

    private function accionCopiarEnlace(): Action
    {
        return Action::make('copiarEnlace')
            ->label('Copiar enlace')
            ->icon('heroicon-o-link')
            ->visible(fn (DocumentoContrato $r) => $r->enlaceFirma() !== null && ! $r->anulado())
            ->authorize(fn () => auth()->user()->can('create', DocumentoContrato::class))
            ->modalHeading('Enlace de firma')
            ->modalDescription('Compártalo solo con quien debe firmar. Para firmar, el sistema le pedirá un código enviado a su celular o correo.')
            ->schema(fn (DocumentoContrato $r) => [
                TextInput::make('enlace')->hiddenLabel()->default($r->enlaceFirma())->readOnly()->copyable(copyMessage: 'Enlace copiado'),
            ])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar');
    }

    private function accionHistorial(): Action
    {
        return Action::make('historial')
            ->label('Historial')
            ->icon('heroicon-o-clock')
            ->modalHeading('Historial del documento')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->schema(fn (DocumentoContrato $r) => [
                Callout::make('Huella del contenido (SHA-256)')
                    ->info()
                    ->description($r->huella_contenido.($r->huella_pdf ? " · PDF firmado: {$r->huella_pdf}" : '')),
                Section::make('Eventos')->schema([
                    RepeatableEntry::make('eventos')
                        ->hiddenLabel()
                        ->state(fn () => $r->eventos()->with('usuario')->get()->map(fn (EventoDocumentoContrato $e) => [
                            'fecha' => $e->created_at?->format('d/m/Y h:i:s a'),
                            'evento' => $e->nombre,
                            'quien' => $e->usuario?->name ?? ($e->ip ? 'Cliente · IP '.$e->ip : '—'),
                            'detalle' => collect($e->detalle ?? [])->map(fn ($v, $k) => is_array($v) ? implode(', ', $v) : "{$k}: {$v}")->implode(' · ') ?: '—',
                        ])->all())
                        ->columns(['default' => 2, 'md' => 4])
                        ->schema([
                            TextEntry::make('fecha')->hiddenLabel(),
                            TextEntry::make('evento')->hiddenLabel()->weight('bold'),
                            TextEntry::make('quien')->hiddenLabel(),
                            TextEntry::make('detalle')->hiddenLabel(),
                        ]),
                ]),
                Section::make('Mensajes')->schema([
                    RepeatableEntry::make('envios')
                        ->hiddenLabel()
                        ->state(fn () => $r->envios()->get()->map(fn (EnvioMensaje $m) => [
                            'fecha' => $m->created_at->format('d/m/Y h:i a'),
                            'tipo' => $m->tipo_nombre,
                            'canal' => ($m->canal === 'sms' ? 'SMS ' : 'Correo ').$m->destino,
                            'estado' => ucfirst($m->estado).($m->error ? ' · '.$m->error : ''),
                        ])->all())
                        ->placeholder('Sin mensajes')
                        ->columns(['default' => 2, 'md' => 4])
                        ->schema([
                            TextEntry::make('fecha')->hiddenLabel(),
                            TextEntry::make('tipo')->hiddenLabel(),
                            TextEntry::make('canal')->hiddenLabel(),
                            TextEntry::make('estado')->hiddenLabel(),
                        ]),
                ]),
            ]);
    }

    private function accionAnular(): Action
    {
        return Action::make('anular')
            ->label('Anular documento')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (DocumentoContrato $r) => ! $r->anulado())
            ->authorize(fn (DocumentoContrato $r) => auth()->user()->can('anular', $r))
            ->requiresConfirmation()
            ->modalDescription(fn (DocumentoContrato $r) => $r->firmado()
                ? 'El contrato ya está firmado. Al anularlo, el documento firmado deja de ser válido (la verificación pública lo mostrará anulado). Úselo solo si hubo un error.'
                : 'El enlace de firma deja de funcionar. Después podrá corregir el contrato y emitirlo de nuevo.')
            ->schema([Textarea::make('motivo')->label('Motivo')->required()->rows(2)])
            ->action(function (DocumentoContrato $record, array $data, Action $action): void {
                ErroresDeNegocio::intentar($action, fn () => app(AnularDocumentoContrato::class)($record, $data['motivo']));
                $this->dispatch('contrato-actualizado');
            })
            ->successNotificationTitle('Documento anulado');
    }
}
