<?php

namespace App\Filament\App\Resources\PlantillasContrato;

use App\Filament\App\Resources\PlantillasContrato\Pages\ListarPlantillas;
use App\Filament\App\Resources\PlantillasContrato\Pages\NuevaVersion;
use App\Filament\App\Resources\PlantillasContrato\Pages\VerPlantilla;
use App\Models\PlantillaContrato;
use App\Support\Contratos\VariablesContrato;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Plantilla del contrato de prestación de servicios, con versiones. Los datos variables se
 * insertan desde la lista del editor (botón de variables), nunca escritos a mano.
 */
class PlantillaContratoResource extends Resource
{
    protected static ?string $model = PlantillaContrato::class;

    protected static ?string $slug = 'plantilla-contrato';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-duplicate';

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 60;

    protected static ?string $navigationLabel = 'Plantilla de contrato';

    protected static ?string $modelLabel = 'versión de la plantilla';

    protected static ?string $pluralModelLabel = 'versiones de la plantilla de contrato';

    protected static ?string $tenantRelationshipName = 'plantillasContrato';

    protected static ?string $breadcrumb = 'Plantilla de contrato';

    public static function getRecordTitle(?Model $record): string
    {
        return $record ? "Plantilla de contrato · versión {$record->version_formateada}" : 'Plantilla de contrato';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Control del documento')
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextInput::make('codigo_formato')->label('Código del formato')->maxLength(40)
                        ->placeholder('GPS-PR01-FT03')
                        ->helperText('Aparece en el encabezado de cada página.'),
                    TextInput::make('version')->label('Versión')->numeric()->minValue(1)->required()
                        ->helperText('Debe ser mayor que la vigente.'),
                    TextInput::make('titulo')->label('Título del formato')->maxLength(255)->required(),
                    Textarea::make('notas_cambio')->label('¿Qué cambió en esta versión?')->rows(2)->columnSpanFull(),
                ]),
            Section::make('Texto del contrato')
                ->description(new HtmlString('Para poner un dato del contrato (contratante, fechas, valor…) use el botón de variables <strong>{+}</strong> de la barra y elija la variable de la lista. No escriba los datos a mano. El bloque de firmas se agrega solo al final.'))
                ->columnSpanFull()
                ->schema([
                    RichEditor::make('contenido')
                        ->hiddenLabel()
                        ->required()
                        ->mergeTags(VariablesContrato::etiquetas())
                        ->noMergeTagSearchResultsMessage('No hay variables con ese nombre')
                        ->toolbarButtons([
                            ['bold', 'italic', 'underline'],
                            ['h2', 'h3'],
                            ['alignStart', 'alignCenter', 'alignEnd'],
                            ['bulletList', 'orderedList'],
                            ['mergeTags'],
                            ['undo', 'redo'],
                        ])
                        ->extraInputAttributes(['style' => 'min-height: 60vh']),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->columns(['default' => 2, 'md' => 4])
                ->schema([
                    TextEntry::make('version_formateada')->label('Versión')->badge()
                        ->color(fn (PlantillaContrato $record) => $record->esVigente() ? 'success' : 'gray')
                        ->helperText(fn (PlantillaContrato $record) => $record->esVigente() ? 'Vigente' : 'Reemplazada'),
                    TextEntry::make('codigo_formato')->label('Código')->placeholder('—'),
                    TextEntry::make('usuario.name')->label('Creada por')->placeholder('—')
                        ->helperText(fn (PlantillaContrato $record) => $record->created_at->format('d/m/Y h:i a')),
                    TextEntry::make('notas_cambio')->label('Cambios')->placeholder('—'),
                ]),
            Section::make('Texto')
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('contenido')
                        ->hiddenLabel()
                        ->html()
                        ->formatStateUsing(fn (string $state) => self::conEtiquetas($state)),
                ]),
        ]);
    }

    /** Muestra cada variable como [Etiqueta] resaltada. */
    public static function conEtiquetas(string $contenido): HtmlString
    {
        $etiquetas = collect(VariablesContrato::etiquetas())
            ->map(fn (string $etiqueta) => '⟦'.$etiqueta.'⟧')
            ->all();

        $html = RichContentRenderer::make($contenido)->mergeTags($etiquetas)->toHtml();

        return new HtmlString('<div class="ruta-plantilla">'.preg_replace(
            '/⟦([^⟧]+)⟧/u',
            '<mark style="background:#FDEEE6;color:#9A3412;border-radius:4px;padding:0 4px">$1</mark>',
            $html,
        ).'</div>');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('usuario'))
            ->defaultSort('version', 'desc')
            ->recordUrl(fn (PlantillaContrato $record) => static::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('version_formateada')->label('Versión')->badge()
                    ->color(fn (PlantillaContrato $record) => $record->esVigente() ? 'success' : 'gray')
                    ->description(fn (PlantillaContrato $record) => $record->esVigente() ? 'Vigente' : null),
                TextColumn::make('codigo_formato')->label('Código')->placeholder('—'),
                TextColumn::make('notas_cambio')->label('Cambios')->placeholder('—')->limit(80)->wrap(),
                TextColumn::make('usuario.name')->label('Creada por')->placeholder('—')->visibleFrom('md'),
                TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y h:i a')->visibleFrom('md'),
            ])
            ->recordActions([ViewAction::make()->iconButton()]);
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListarPlantillas::route('/'),
            'create' => NuevaVersion::route('/nueva-version'),
            'view' => VerPlantilla::route('/{record}'),
        ];
    }
}
