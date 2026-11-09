<?php

namespace App\Filament\App\Resources\Conductores\RelationManagers;

use App\Enums\TipoDocumentoConductor;
use App\Filament\App\Resources\Conductores\Schemas\DocumentoConductorForm;
use App\Models\DocumentoConductor;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Documentos del conductor con su historial: renovar = registrar uno nuevo del mismo tipo.
 */
class DocumentosRelationManager extends RelationManager
{
    protected static string $relationship = 'documentos';

    protected static ?string $title = 'Documentos';

    protected static ?string $modelLabel = 'documento';

    protected static ?string $pluralModelLabel = 'documentos';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-document-check';

    private ?array $idsVigentes = null;

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()->can('viewAny', DocumentoConductor::class);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'md' => 2])->components(DocumentoConductorForm::campos());
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('categoriaLicencia'))
            ->defaultSort('fecha_vencimiento', 'desc')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->defaultGroup(Group::make('tipo')->label('Documento')->getTitleFromRecordUsing(fn (DocumentoConductor $r) => $r->tipo->getLabel()))
            ->columns([
                TextColumn::make('numero')->label('Número')->placeholder('—')->searchable(),
                TextColumn::make('categoriaLicencia.codigo')->label('Categoría')->badge()->color('primary')->placeholder('—'),
                TextColumn::make('entidad')->label('Expide')->placeholder('—')->visibleFrom('lg'),
                TextColumn::make('fecha_expedicion')->label('Expedición / pago')->date('d/m/Y')->placeholder('—')->visibleFrom('md'),
                TextColumn::make('fecha_vencimiento')->label('Vence')->date('d/m/Y')->sortable()->weight('bold'),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->state(fn (DocumentoConductor $r) => $this->esVigente($r) ? $r->estado() : 'Reemplazado')
                    ->color(fn (DocumentoConductor $r) => $this->esVigente($r) ? $r->estado()->getColor() : 'gray'),
            ])
            ->filters([
                SelectFilter::make('tipo')->label('Documento')->options(TipoDocumentoConductor::class),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Registrar documento')
                    ->modalHeading('Registrar documento o renovación')
                    ->after(fn () => $this->avisarCambio()),
            ])
            ->recordActions([
                Action::make('archivo')->iconButton()
                    ->label('Ver archivo')
                    ->icon('heroicon-o-paper-clip')
                    ->color('gray')
                    ->visible(fn (DocumentoConductor $r) => $r->tieneArchivo())
                    ->url(fn (DocumentoConductor $r) => $r->urlArchivo())
                    ->openUrlInNewTab(),
                Action::make('renovar')->iconButton()
                    ->label('Renovar')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (DocumentoConductor $r) => $this->esVigente($r) && auth()->user()->can('create', DocumentoConductor::class))
                    ->modalHeading(fn (DocumentoConductor $r) => "Renovar {$r->tipo->getLabel()}")
                    ->schema(DocumentoConductorForm::campos(tipoFijo: true))
                    ->fillForm(fn (DocumentoConductor $r) => [
                        'tipo' => $r->tipo->value,
                        'categoria_licencia_id' => $r->categoria_licencia_id,
                        'entidad' => $r->entidad,
                    ])
                    ->action(function (DocumentoConductor $r, array $data): void {
                        $this->getOwnerRecord()->documentos()->create([...$data, 'tipo' => $r->tipo]);
                        $this->avisarCambio();
                    })
                    ->modalSubmitActionLabel('Guardar renovación')
                    ->successNotificationTitle('Documento renovado'),
                EditAction::make()->iconButton()->after(fn () => $this->avisarCambio()),
                DeleteAction::make()->iconButton()->after(fn () => $this->avisarCambio()),
            ]);
    }

    /** Actualiza el semáforo de la hoja de vida (página VerConductor). */
    private function avisarCambio(): void
    {
        $this->idsVigentes = null;
        $this->getOwnerRecord()->unsetRelation('documentos');
        $this->dispatch('documentos-actualizados');
    }

    private function esVigente(DocumentoConductor $documento): bool
    {
        $this->idsVigentes ??= DocumentoConductor::query()
            ->vigentes()
            ->where('conductor_id', $this->getOwnerRecord()->getKey())
            ->pluck('id')
            ->all();

        return in_array($documento->getKey(), $this->idsVigentes, true);
    }
}
