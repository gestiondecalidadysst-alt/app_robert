<?php

namespace App\Filament\App\Resources\EmpresasConvenio;

use App\Filament\App\Resources\EmpresasConvenio\Pages\ManageEmpresasConvenio;
use App\Filament\Compartido\CamposComunes;
use App\Models\EmpresaConvenio;
use App\Support\Nit;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class EmpresaConvenioResource extends Resource
{
    protected static ?string $model = EmpresaConvenio::class;

    protected static ?string $slug = 'empresas-convenio';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 50;

    protected static ?string $modelLabel = 'empresa en convenio';

    protected static ?string $pluralModelLabel = 'empresas en convenio';

    protected static ?string $navigationLabel = 'Empresas en convenio';

    protected static ?string $breadcrumb = 'Empresas en convenio';

    protected static ?string $recordTitleAttribute = 'razon_social';

    protected static ?string $tenantRelationshipName = 'empresasConvenio';

    public static function form(Schema $schema): Schema
    {
        [$nit, $dv] = CamposComunes::nit();

        return $schema
            ->columns(4)
            ->components([
                $nit->columnSpan(3)->scopedUnique(ignoreRecord: true),
                $dv->columnSpan(1),
                TextInput::make('razon_social')->label('Razón social')->required()->maxLength(255)->columnSpanFull(),
                Select::make('tipo_vinculacion')
                    ->label('Tipo de vinculación')
                    ->options(EmpresaConvenio::TIPOS_VINCULACION)
                    ->required()
                    ->columnSpan(3),
                Toggle::make('activo')->default(true)->inline(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('razon_social')
            ->columns([
                TextColumn::make('razon_social')->label('Razón social')->searchable()->sortable()->weight('bold'),
                TextColumn::make('nit')
                    ->label('NIT')
                    ->searchable()
                    ->formatStateUsing(fn (EmpresaConvenio $record) => Nit::formatear($record->nit, $record->dv)),
                TextColumn::make('tipo_vinculacion')
                    ->label('Vinculación')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => EmpresaConvenio::TIPOS_VINCULACION[$state] ?? $state),
                IconColumn::make('activo')->boolean(),
            ])
            ->filters([
                SelectFilter::make('tipo_vinculacion')->label('Vinculación')->options(EmpresaConvenio::TIPOS_VINCULACION),
            ])
            ->recordActions([
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEmpresasConvenio::route('/'),
        ];
    }
}
