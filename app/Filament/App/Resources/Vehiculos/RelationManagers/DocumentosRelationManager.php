<?php

namespace App\Filament\App\Resources\Vehiculos\RelationManagers;

use App\Enums\TipoDocumentoVehiculo;
use App\Filament\App\Resources\Vehiculos\Schemas\DocumentoForm;
use App\Models\DocumentoVehiculo;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Documentos del vehículo con su historial: renovar = registrar uno nuevo del mismo tipo.
 */
class DocumentosRelationManager extends RelationManager
{
    protected static string $relationship = 'documentos';

    protected static ?string $title = 'Documentos';

    protected static ?string $modelLabel = 'documento';

    protected static ?string $pluralModelLabel = 'documentos';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-document-check';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()->can('viewAny', DocumentoVehiculo::class);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'md' => 2])->components(DocumentoForm::campos());
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('aseguradora'))
            ->defaultSort('fecha_vencimiento', 'desc')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->defaultGroup(Group::make('tipo')->label('Documento')->getTitleFromRecordUsing(fn (DocumentoVehiculo $r) => $r->tipo->getLabel()))
            ->columns([
                TextColumn::make('numero')->label('Número')->placeholder('—')->searchable(),
                TextColumn::make('emisor')->label('Expide')->state(fn (DocumentoVehiculo $r) => $r->emisor())->placeholder('—')->visibleFrom('lg'),
                TextColumn::make('fecha_expedicion')->label('Expedición')->date('d/m/Y')->placeholder('—')->visibleFrom('md'),
                TextColumn::make('fecha_vencimiento')->label('Vence')->date('d/m/Y')->sortable()->weight('bold'),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->state(fn (DocumentoVehiculo $r) => $this->esVigente($r) ? $r->estado() : 'Reemplazado')
                    ->color(fn (DocumentoVehiculo $r) => $this->esVigente($r) ? $r->estado()->getColor() : 'gray'),
            ])
            ->filters([
                SelectFilter::make('tipo')->label('Documento')->options(TipoDocumentoVehiculo::class),
                TernaryFilter::make('solo_vigentes')
                    ->label('Mostrar')
                    ->placeholder('Todo el historial')
                    ->trueLabel('Solo vigentes')
                    ->falseLabel('Solo reemplazados')
                    ->queries(
                        true: fn (Builder $q) => $q->vigentes(),
                        false: fn (Builder $q) => $q->whereNotIn('id', DocumentoVehiculo::query()->vigentes()->where('vehiculo_id', $this->getOwnerRecord()->getKey())->select('id')),
                        blank: fn (Builder $q) => $q,
                    ),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Registrar documento')
                    ->modalHeading('Registrar documento o renovación')
                    ->after(fn () => $this->avisarCambio()),
            ])
            ->recordActions([
                Action::make('archivo')
                    ->label('Ver archivo')
                    ->icon('heroicon-o-paper-clip')
                    ->color('gray')
                    ->visible(fn (DocumentoVehiculo $r) => $r->tieneArchivo())
                    ->url(fn (DocumentoVehiculo $r) => Storage::disk(DocumentoVehiculo::DISCO)->temporaryUrl($r->archivo_path, now()->addMinutes(10)))
                    ->openUrlInNewTab(),
                Action::make('renovar')
                    ->label('Renovar')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (DocumentoVehiculo $r) => $this->esVigente($r) && auth()->user()->can('create', DocumentoVehiculo::class))
                    ->modalHeading(fn (DocumentoVehiculo $r) => "Renovar {$r->tipo->getLabel()}")
                    ->schema(DocumentoForm::campos(tipoFijo: true))
                    ->fillForm(fn (DocumentoVehiculo $r) => [
                        'tipo' => $r->tipo->value,
                        'aseguradora_id' => $r->aseguradora_id,
                        'entidad' => $r->entidad,
                    ])
                    ->action(function (DocumentoVehiculo $r, array $data): void {
                        $this->getOwnerRecord()->documentos()->create([...$data, 'tipo' => $r->tipo]);
                        $this->avisarCambio();
                    })
                    ->modalSubmitActionLabel('Guardar renovación')
                    ->successNotificationTitle('Documento renovado'),
                EditAction::make()->after(fn () => $this->avisarCambio()),
                DeleteAction::make()->after(fn () => $this->avisarCambio()),
            ]);
    }

    /** Actualiza el semáforo de la hoja de vida (página VerVehiculo). */
    private function avisarCambio(): void
    {
        $this->idsVigentes = null;
        $this->getOwnerRecord()->unsetRelation('documentos');
        $this->dispatch('documentos-actualizados');
    }

    private ?array $idsVigentes = null;

    private function esVigente(DocumentoVehiculo $documento): bool
    {
        $this->idsVigentes ??= DocumentoVehiculo::query()
            ->vigentes()
            ->where('vehiculo_id', $this->getOwnerRecord()->getKey())
            ->pluck('id')
            ->all();

        return in_array($documento->getKey(), $this->idsVigentes, true);
    }
}
