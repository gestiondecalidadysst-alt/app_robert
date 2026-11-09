<?php

namespace App\Filament\App\Resources\Auditoria;

use App\Filament\App\Resources\Auditoria\Pages\ListarAuditoria;
use App\Models\Actividad;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Auditoría de cambios de la empresa (solo lectura).
 */
class ActividadResource extends Resource
{
    protected static ?string $model = Actividad::class;

    protected static ?string $slug = 'auditoria';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 90;

    protected static ?string $modelLabel = 'registro de auditoría';

    protected static ?string $pluralModelLabel = 'auditoría';

    protected static ?string $navigationLabel = 'Auditoría';

    protected static ?string $breadcrumb = 'Auditoría';

    protected static ?string $tenantRelationshipName = 'actividades';

    /** Nombres legibles de los registros auditados (coinciden con class_basename del modelo). */
    public const REGISTROS = [
        'Vehiculo' => 'Vehículo',
        'Conductor' => 'Conductor',
        'Contrato' => 'Contrato',
        'Fuec' => 'FUEC',
        'Cotizacion' => 'Cotización',
        'Cliente' => 'Cliente',
        'Propietario' => 'Propietario',
        'DocumentoVehiculo' => 'Documento de vehículo',
        'DocumentoConductor' => 'Documento de conductor',
        'DocumentoContrato' => 'Documento de contrato',
        'PagoContrato' => 'Pago de contrato',
        'PlantillaContrato' => 'Plantilla de contrato',
        'Servicio' => 'Servicio',
        'AsignacionVehiculo' => 'Asignación de vehículo',
        'Ruta' => 'Ruta',
        'Inspeccion' => 'Inspección',
        'ItemChequeo' => 'Ítem de chequeo',
        'Mantenimiento' => 'Mantenimiento',
        'ProgramaMantenimiento' => 'Programa de mantenimiento',
        'RutinaMantenimiento' => 'Rutina de mantenimiento',
        'PlantillaMantenimiento' => 'Plantilla de mantenimiento',
        'NovedadConductor' => 'Novedad de conductor',
        'NovedadVehiculo' => 'Novedad de vehículo',
        'PlanAccion' => 'Plan de acción',
        'Oficina' => 'Oficina',
        'User' => 'Usuario',
        'ValorAdministracion' => 'Valor de administración',
        'EmpresaConvenio' => 'Empresa en convenio',
        'Empresa' => 'Empresa',
        'Catalogo' => 'Catálogo',
    ];

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(3)->schema([
                TextEntry::make('created_at')->label('Fecha')->dateTime('d/m/Y h:i a'),
                TextEntry::make('causer.name')->label('Usuario')->placeholder('Sistema'),
                TextEntry::make('evento_legible')->label('Acción')->badge(),
            ]),
            TextEntry::make('cambios')
                ->label('Cambios')
                ->columnSpanFull()
                ->state(fn (Actividad $record) => static::resumenCambios($record))
                ->html(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('causer'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y h:i a')->sortable(),
                TextColumn::make('causer.name')->label('Usuario')->placeholder('Sistema')->searchable(),
                TextColumn::make('evento_legible')
                    ->label('Acción')
                    ->badge()
                    ->color(fn (Actividad $record) => match ($record->event) {
                        'created' => 'success',
                        'deleted' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('log_name')
                    ->label('Registro')
                    ->formatStateUsing(fn (?string $state) => static::REGISTROS[$state] ?? $state),
                TextColumn::make('subject_id')->label('ID')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('log_name')->label('Registro')->options(static::REGISTROS),
                SelectFilter::make('event')->label('Acción')->options([
                    'created' => 'Creó',
                    'updated' => 'Modificó',
                    'deleted' => 'Eliminó',
                ]),
                Filter::make('fecha')
                    ->schema([
                        DatePicker::make('desde')->native(false)->displayFormat('d/m/Y'),
                        DatePicker::make('hasta')->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['desde'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['hasta'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))),
            ])
            ->recordActions([
                ViewAction::make()->iconButton(),
            ]);
    }

    public static function resumenCambios(Actividad $actividad): HtmlString
    {
        $nuevos = (array) ($actividad->properties['attributes'] ?? []);
        $anteriores = (array) ($actividad->properties['old'] ?? []);

        if ($nuevos === [] && $anteriores === []) {
            return new HtmlString('<em>Sin detalle</em>');
        }

        $filas = '';
        foreach (array_unique([...array_keys($anteriores), ...array_keys($nuevos)]) as $campo) {
            $antes = e(is_scalar($anteriores[$campo] ?? null) ? (string) $anteriores[$campo] : json_encode($anteriores[$campo] ?? null));
            $despues = e(is_scalar($nuevos[$campo] ?? null) ? (string) $nuevos[$campo] : json_encode($nuevos[$campo] ?? null));
            $filas .= '<tr><td style="padding:4px 12px 4px 0;font-weight:600">'.e($campo)."</td><td style=\"padding:4px 12px;color:#b42318\">{$antes}</td><td style=\"padding:4px 0;color:#0f6b45\">{$despues}</td></tr>";
        }

        return new HtmlString('<table style="font-size:13px"><thead><tr><th style="text-align:left">Campo</th><th style="text-align:left;padding:0 12px">Antes</th><th style="text-align:left">Después</th></tr></thead><tbody>'.$filas.'</tbody></table>');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListarAuditoria::route('/'),
        ];
    }
}
