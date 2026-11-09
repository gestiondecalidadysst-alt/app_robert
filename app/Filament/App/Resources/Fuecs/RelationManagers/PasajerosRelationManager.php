<?php

namespace App\Filament\App\Resources\Fuecs\RelationManagers;

use App\Actions\Operacion\EnviarEnlacePasajeros;
use App\Actions\Operacion\ImportarPasajeros;
use App\Models\Fuec;
use App\Models\PasajeroFuec;
use App\Support\Pdf\Imagenes;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Listado de pasajeros del FUEC (Turismo / Grupo específico). Los pasajeros se autorregistran
 * por el enlace público; aquí el operador los ve, comparte el enlace/QR y cierra el listado.
 */
class PasajerosRelationManager extends RelationManager
{
    protected static string $relationship = 'pasajeros';

    protected static ?string $title = 'Listado de pasajeros';

    protected static ?string $modelLabel = 'pasajero';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-user-group';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Fuec && $ownerRecord->tieneListadoPasajeros();
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    private function fuec(): Fuec
    {
        /** @var Fuec $f */
        $f = $this->getOwnerRecord();

        return $f;
    }

    public function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Aún no hay pasajeros')
            ->emptyStateDescription('Comparte el enlace de registro con los pasajeros para que se agreguen.')
            ->emptyStateIcon('heroicon-o-user-group')
            ->columns([
                TextColumn::make('orden')->label('#')->alignCenter()->width('1%'),
                TextColumn::make('nombre')->label('Nombre')->weight('bold')->searchable()
                    ->description(fn (PasajeroFuec $r) => $r->es_menor ? 'Menor · acudiente: '.$r->acudiente_nombre : null),
                TextColumn::make('documento')->label('Documento')->searchable()
                    ->formatStateUsing(fn (PasajeroFuec $r) => $r->tipo_documento.' '.$r->documento),
                TextColumn::make('telefono')->label('Teléfono')->placeholder('—')->visibleFrom('md'),
                TextColumn::make('es_menor')->label('Tipo')->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Menor' : 'Adulto')
                    ->color(fn ($state) => $state ? 'warning' : 'gray')->visibleFrom('md'),
                TextColumn::make('registrado_at')->label('Registrado')->dateTime('d/m/Y h:i a')->visibleFrom('lg'),
            ])
            ->headerActions([
                Action::make('enlace')
                    ->label('Enlace de registro')
                    ->icon('heroicon-o-link')
                    ->modalHeading('Enlace de registro de pasajeros')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalContent(fn () => view('filament.pasajeros.enlace', [
                        'url' => $this->fuec()->urlListadoPasajeros(),
                        'qr' => Imagenes::qr($this->fuec()->urlListadoPasajeros() ?? '', 5),
                        'abierto' => $this->fuec()->listadoAbierto(),
                        'cupos' => $this->fuec()->cuposDisponibles(),
                    ])),
                Action::make('enviarCliente')
                    ->label('Enviar al cliente')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->visible(fn () => $this->fuec()->listadoAbierto())
                    ->requiresConfirmation()
                    ->modalHeading('Enviar el enlace al cliente del contrato')
                    ->modalDescription('Se enviará el enlace de registro por correo y/o SMS al cliente, para que lo comparta con los pasajeros.')
                    ->action(function (): void {
                        try {
                            $canales = app(EnviarEnlacePasajeros::class)($this->fuec());
                            Notification::make()->success()
                                ->title('Enlace enviado')
                                ->body('Se envió por '.implode(' y ', $canales).'.')
                                ->send();
                        } catch (ValidationException $e) {
                            Notification::make()->danger()
                                ->title('No se pudo enviar')
                                ->body(collect($e->errors())->flatten()->first())
                                ->send();
                        }
                    }),
                Action::make('importar')
                    ->label('Importar Excel')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('gray')
                    ->visible(fn () => $this->fuec()->listadoAbierto())
                    ->modalHeading('Importar pasajeros desde Excel o CSV')
                    ->modalDescription('Columnas: nombre, tipo_documento, documento, telefono, es_menor, acudiente_nombre, acudiente_documento. La primera fila puede ser el encabezado.')
                    ->schema([
                        FileUpload::make('archivo')
                            ->label('Archivo (.xlsx o .csv)')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'text/csv', 'text/plain', 'application/csv',
                            ])
                            ->storeFiles(false)
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $archivo = $data['archivo'];
                        $ruta = is_array($archivo) ? reset($archivo) : $archivo;

                        $resultado = app(ImportarPasajeros::class)(
                            $this->fuec(),
                            $ruta->getRealPath(),
                        );

                        $cuerpo = "Importados: {$resultado['importados']} · Omitidos: {$resultado['omitidos']}.";
                        if ($resultado['errores'] !== []) {
                            $cuerpo .= ' '.implode(' ', array_slice($resultado['errores'], 0, 3));
                        }

                        Notification::make()
                            ->title($resultado['importados'] > 0 ? 'Importación completada' : 'No se importaron pasajeros')
                            ->body($cuerpo)
                            ->color($resultado['importados'] > 0 ? 'success' : 'warning')
                            ->send();
                    }),
                Action::make('cerrar')
                    ->label('Cerrar listado')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->visible(fn () => $this->fuec()->listadoAbierto())
                    ->requiresConfirmation()
                    ->modalHeading('Cerrar el listado de pasajeros')
                    ->modalDescription('Nadie más podrá registrarse por el enlace. Podrás reabrirlo si hace falta.')
                    ->action(function (): void {
                        $this->fuec()->cerrarListadoPasajeros(auth()->id());
                        Notification::make()->success()->title('Listado cerrado')->send();
                    }),
                Action::make('reabrir')
                    ->label('Reabrir listado')
                    ->icon('heroicon-o-lock-open')
                    ->color('gray')
                    ->visible(fn () => $this->fuec()->listadoCerrado())
                    ->requiresConfirmation()
                    ->modalHeading('Reabrir el listado de pasajeros')
                    ->modalDescription('Los pasajeros podrán volver a registrarse por el enlace.')
                    ->action(function (): void {
                        $this->fuec()->reabrirListadoPasajeros();
                        Notification::make()->success()->title('Listado reabierto')->send();
                    }),
            ])
            ->recordActions([
                DeleteAction::make()->iconButton()
                    ->modalHeading('Quitar pasajero del listado'),
            ]);
    }
}
