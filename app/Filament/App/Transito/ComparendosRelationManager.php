<?php

namespace App\Filament\App\Transito;

use App\Actions\Transito\ConsultarTransito;
use App\Enums\TipoConsultaTransito;
use App\Models\ComparendoTransito;
use App\Models\Conductor;
use App\Models\Empresa;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Comparendos, licencia y RUNT de un conductor o vehículo: resumen de la última consulta, botón
 * «Consultar» por tipo con el precio a la vista (cada consulta cuesta) y si entra en las automáticas.
 */
class ComparendosRelationManager extends RelationManager
{
    protected static string $relationship = 'comparendosTransito';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-magnifying-glass-circle';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return $ownerRecord instanceof Conductor ? 'Comparendos y licencia' : 'Multas y RUNT';
    }

    /** Tipos de consulta que la empresa usa para este registro. */
    public static function tipos(Model $registro): array
    {
        $empresa = Empresa::find($registro->empresa_id);
        $tipos = $registro instanceof Conductor ? TipoConsultaTransito::deConductor() : TipoConsultaTransito::deVehiculo();

        return array_values(array_filter($tipos, fn ($t) => $empresa?->usaTransito($t)));
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (auth()->user()?->can('ver_consultas_transito') ?? false) && self::tipos($ownerRecord) !== [];
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    private function resumen(): string
    {
        $r = $this->getOwnerRecord()->transito_resumen ?? [];
        $f = fn (?string $iso) => $iso ? \Illuminate\Support\Carbon::parse($iso)->timezone(config('app.timezone'))->format('d/m/Y') : null;
        $partes = [];
        if ($c = $r['comparendos'] ?? null) {
            $partes[] = ($c['pendientes'] ? "{$c['pendientes']} pendiente(s) por $ " . number_format((float) $c['deuda'], 0, ',', '.') : 'Sin comparendos pendientes')
                . ($c['acuerdos'] ? " · {$c['acuerdos']} en acuerdo de pago" : '') . ' (consultado ' . $f($c['consultado_at']) . ')';
        }
        if ($l = $r['licencia'] ?? null) {
            $partes[] = 'Licencia: ' . ($l['suspendida'] ? 'SUSPENDIDA' : ($l['estado'] ?? '—')) . (($l['categorias'] ?? []) ? ' · ' . collect($l['categorias'])->map(fn ($c) => is_array($c) ? trim(($c['categoria'] ?? '') . ' ' . ($c['estado'] ?? '')) : $c)->implode(', ') : '') . ' (consultado ' . $f($l['consultado_at']) . ')';
        }
        if ($u = $r['runt'] ?? null) {
            $partes[] = 'RUNT: SOAT vence ' . ($u['soat_vence'] ?? '—') . ' · Técnico-mecánica vence ' . ($u['rtm_vence'] ?? '—')
                . (($u['diferencias'] ?? []) ? ' · ' . implode(' ', $u['diferencias']) : '') . ' (consultado ' . $f($u['consultado_at']) . ')';
        }

        return $partes ? implode("\n", $partes) : 'Aún no se ha consultado. Cada consulta tiene costo: hágala solo cuando la necesite.';
    }

    public function table(Table $table): Table
    {
        $registro = $this->getOwnerRecord();
        $puede = auth()->user()?->can('crear_consultas_transito') ?? false;

        return $table
            ->heading(self::getTitle($registro, static::class))
            ->description(fn () => $this->resumen())
            ->modifyQueryUsing(fn ($query) => $query->orderByRaw('ya_no_aparece_at is not null')->orderByDesc('fecha'))
            ->emptyStateHeading('Sin comparendos registrados')
            ->emptyStateDescription('Aparecen aquí después de consultar.')
            ->columns([
                TextColumn::make('fecha')->date('d/m/Y')->placeholder('—'),
                TextColumn::make('numero')->label('Número')->searchable(),
                TextColumn::make('infraccion')->label('Infracción')->wrap()->limit(90)->description(fn (ComparendoTransito $r) => $r->codigo),
                TextColumn::make('placa')->placeholder('—')->visibleFrom('md'),
                TextColumn::make('organismo')->wrap()->limit(40)->visibleFrom('lg'),
                TextColumn::make('valor')->formatStateUsing(fn ($state) => '$ ' . number_format((float) $state, 0, ',', '.'))->alignEnd(),
                TextColumn::make('estado')->badge()->formatStateUsing(fn ($state, ComparendoTransito $r) => $r->etiquetaEstado())
                    ->color(fn ($state, ComparendoTransito $r) => $r->ya_no_aparece_at ? 'gray' : match ($state) { 'pendiente' => 'danger', 'acuerdo' => 'warning', 'pagada' => 'success', default => 'gray' }),
            ])
            ->filters([
                TernaryFilter::make('vigentes')->label('Mostrar')->placeholder('Todos')->trueLabel('Solo los de la última consulta')->falseLabel('Solo los que ya no aparecen')
                    ->default(true)
                    ->queries(true: fn ($query) => $query->whereNull('ya_no_aparece_at'), false: fn ($query) => $query->whereNotNull('ya_no_aparece_at')),
            ])
            ->headerActions([
                ...array_map(fn (TipoConsultaTransito $t) => $this->accionConsultar($t)->visible($puede), self::tipos($registro)),
                Action::make('automatica')
                    ->label(fn () => $this->getOwnerRecord()->transito_auto ? 'En consultas automáticas' : 'Incluir en consultas automáticas')
                    ->icon(fn () => $this->getOwnerRecord()->transito_auto ? 'heroicon-o-check-circle' : 'heroicon-o-arrow-path')
                    ->color(fn () => $this->getOwnerRecord()->transito_auto ? 'success' : 'gray')
                    ->visible($puede && (bool) Empresa::find($registro->empresa_id)?->transito_auto)
                    ->requiresConfirmation()
                    ->modalDescription(fn () => $this->getOwnerRecord()->transito_auto
                        ? 'Deja de consultarse solo. Podrá seguir consultándolo a mano.'
                        : 'Se consultará solo con la frecuencia que fijó la empresa, y cada consulta con datos se cobra.')
                    ->action(function (): void {
                        $r = $this->getOwnerRecord();
                        $r->forceFill(['transito_auto' => ! $r->transito_auto])->save();
                        Notification::make()->success()->title($r->transito_auto ? 'Incluido en las consultas automáticas' : 'Retirado de las consultas automáticas')->send();
                    }),
            ]);
    }

    /** Lo que encontró la consulta, en una línea, para la notificación. */
    private function resultado(TipoConsultaTransito $tipo): string
    {
        $r = $this->getOwnerRecord()->transito_resumen ?? [];

        return match ($tipo) {
            TipoConsultaTransito::ConductorComparendos, TipoConsultaTransito::VehiculoMultas => ($c = $r['comparendos'] ?? null) && $c['total']
                ? "{$c['total']} registro(s): {$c['pendientes']} pendiente(s), {$c['acuerdos']} en acuerdo de pago. Deuda: $ " . number_format((float) $c['deuda'], 0, ',', '.') . '.'
                : 'Sin comparendos ni multas: está a paz y salvo.',
            TipoConsultaTransito::ConductorLicencia => ($l = $r['licencia'] ?? null)
                ? 'Licencia: ' . ($l['suspendida'] ? 'SUSPENDIDA' : ($l['estado'] ?? '—')) . (($l['categorias'] ?? []) ? ' · ' . collect($l['categorias'])->map(fn ($c) => trim(($c['categoria'] ?? '') . ' ' . ($c['estado'] ?? '') . (($c['vence'] ?? null) ? ' vence ' . $c['vence'] : '')))->implode(', ') : '') . '.'
                : 'Sin datos de licencia.',
            TipoConsultaTransito::VehiculoRunt => ($u = $r['runt'] ?? null)
                ? 'SOAT vence ' . ($u['soat_vence'] ?? '—') . ' · Técnico-mecánica vence ' . ($u['rtm_vence'] ?? '—') . (($u['diferencias'] ?? []) ? ' · ' . implode(' ', $u['diferencias']) : '')
                : 'Sin datos del RUNT.',
        };
    }

    private function accionConsultar(TipoConsultaTransito $tipo): Action
    {
        return Action::make('consultar_' . $tipo->value)
            ->label('Consultar ' . mb_strtolower($tipo->corto()))
            ->icon('heroicon-o-magnifying-glass')
            ->requiresConfirmation()
            ->modalHeading($tipo->getLabel())
            ->modalDescription(function () use ($tipo) {
                $c = ConsultarTransito::cotizar($this->getOwnerRecord(), $tipo);
                $p = fn ($v) => '$ ' . number_format((float) $v, 0, ',', '.');

                return 'Esta consulta cuesta ' . ($c['precio'] !== null ? $p($c['precio']) : '(sin precio)') . ' si trae datos. '
                    . 'Gastado este mes: ' . $p($c['gastado']) . ($c['tope'] !== null ? ' de ' . $p($c['tope']) . ' (tope).' : '.');
            })
            ->modalSubmitActionLabel('Consultar')
            ->action(function () use ($tipo): void {
                try {
                    $consulta = app(ConsultarTransito::class)($this->getOwnerRecord(), $tipo, 'manual', auth()->user());
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title('No se pudo consultar')->body(collect($e->errors())->flatten()->first())->persistent()->send();

                    return;
                }
                $this->getOwnerRecord()->refresh();
                $cobro = $consulta->cobrada ? 'Se cobró $ ' . number_format((float) $consulta->precio, 0, ',', '.') . '.' : 'Sin costo (resultado reciente del proveedor).';

                match ($consulta->estado) {
                    'ok' => Notification::make()->success()->title('Consulta realizada')->body($this->resultado($tipo) . "\n" . $cobro)->persistent()->send(),
                    'sin_datos' => Notification::make()->info()->title('Sin información en el sistema de tránsito')->body('No se cobró.')->send(),
                    default => Notification::make()->danger()->title('La consulta falló')->body(($consulta->error ?: 'Intente más tarde.') . ' No se cobró.')->persistent()->send(),
                };
            });
    }
}
