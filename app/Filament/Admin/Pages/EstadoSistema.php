<?php

namespace App\Filament\Admin\Pages;

use App\Models\EnvioMensaje;
use App\Models\ErrorSistema;
use App\Models\WebhookEntrega;
use App\Support\Sistema\EstadoColas;
use App\Support\Sistema\Latidos;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

/**
 * Estado del sistema (solo superadministrador): procesos de fondo (worker y tareas programadas),
 * cola por fila, trabajos fallidos con reintento, envíos fallidos y errores inesperados agrupados.
 */
class EstadoSistema extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.admin.estado-sistema';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-heart';

    protected static ?string $navigationLabel = 'Estado del sistema';

    protected static ?string $title = 'Estado del sistema';

    protected static ?string $slug = 'estado-sistema';

    protected static ?int $navigationSort = 90;

    public static function getNavigationBadge(): ?string
    {
        $abiertos = ErrorSistema::query()->abiertos()->count();
        $problemas = $abiertos + (Latidos::vivo(Latidos::WORKER) ? 0 : 1) + (Latidos::vivo(Latidos::PROGRAMADAS) ? 0 : 1);

        return $problemas ? (string) $problemas : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('actualizar')->label('Actualizar')->icon('heroicon-o-arrow-path')->color('gray')->action(fn () => null),
        ];
    }

    /** Lo que muestra la vista: procesos, filas, fallidos y envíos. */
    protected function getViewData(): array
    {
        return [
            'procesos' => [
                ['Worker de la cola', Latidos::ultimo(Latidos::WORKER), Latidos::vivo(Latidos::WORKER),
                    'Servicio con CONTAINER_ROLE=worker. Envía SMS, correos y webhooks.'],
                ['Tareas programadas', Latidos::ultimo(Latidos::PROGRAMADAS), Latidos::vivo(Latidos::PROGRAMADAS),
                    'Servicio con CONTAINER_ROLE=scheduler (o el worker, si no existe). Recordatorios, mantenimiento e informe mensual.'],
            ],
            'filas' => EstadoColas::pendientes(),
            'atascados' => EstadoColas::atascados(),
            'fallidos' => $this->fallidos(),
            'envios' => [
                'SMS y correos fallidos (7 días)' => EnvioMensaje::query()->withoutGlobalScopes()->where('estado', 'fallido')->where('created_at', '>=', now()->subDays(7))->count(),
                'Webhooks fallidos (7 días)' => WebhookEntrega::query()->withoutGlobalScopes()->where('estado', 'fallido')->where('created_at', '>=', now()->subDays(7))->count(),
                'Webhooks pendientes' => WebhookEntrega::query()->withoutGlobalScopes()->where('estado', 'pendiente')->count(),
            ],
        ];
    }

    /** @return Collection<int, object> últimos 50 trabajos fallidos */
    public function fallidos(): Collection
    {
        return DB::table('failed_jobs')->orderByDesc('failed_at')->limit(50)->get()->map(fn ($f) => (object) [
            'uuid' => $f->uuid,
            'fila' => $f->queue,
            'trabajo' => EstadoColas::nombreTrabajo($f->payload),
            'fecha' => \Illuminate\Support\Carbon::parse($f->failed_at)->timezone(config('app.timezone')),
            'error' => \Illuminate\Support\Str::limit(strtok((string) $f->exception, "\n"), 220),
        ]);
    }

    public function reintentar(string $uuid): void
    {
        Artisan::call('queue:retry', ['id' => [$uuid]]);
        Notification::make()->success()->title('Trabajo devuelto a la cola')->send();
    }

    public function reintentarTodos(): void
    {
        Artisan::call('queue:retry', ['id' => ['all']]);
        Notification::make()->success()->title('Todos los trabajos fallidos volvieron a la cola')->send();
    }

    public function descartar(string $uuid): void
    {
        Artisan::call('queue:forget', ['id' => $uuid]);
        Notification::make()->success()->title('Trabajo descartado')->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ErrorSistema::query()->with(['usuario', 'empresa']))
            ->heading('Errores inesperados')
            ->description('Agrupados: el mismo error suma ocurrencias. Busque por el código que le comparte un usuario (REF-…).')
            ->defaultSort('ultima_vez', 'desc')
            ->emptyStateHeading('Sin errores registrados')
            ->emptyStateIcon('heroicon-o-check-badge')
            ->columns([
                TextColumn::make('referencia')->searchable()->weight('bold')->copyable()
                    ->description(fn (ErrorSistema $r) => $r->resuelto_at ? 'Resuelto' : null),
                TextColumn::make('mensaje')->searchable()->limit(70)->wrap()
                    ->description(fn (ErrorSistema $r) => "{$r->clase_corta} · {$r->archivo}:{$r->linea}"),
                TextColumn::make('ocurrencias')->label('Veces')->badge()->alignCenter()
                    ->color(fn (int $state) => $state > 10 ? 'danger' : ($state > 1 ? 'warning' : 'gray')),
                TextColumn::make('ultima_vez')->label('Última vez')->since()->sortable()
                    ->description(fn (ErrorSistema $r) => 'Primera: ' . $r->primera_vez->timezone(config('app.timezone'))->format('d/m h:i a')),
                TextColumn::make('empresa.razon_social')->label('Empresa')->placeholder('—')->limit(24)->visibleFrom('lg'),
                TextColumn::make('usuario.name')->label('Usuario')->placeholder('—')->visibleFrom('lg'),
            ])
            ->filters([
                TernaryFilter::make('resuelto_at')->label('Estado')->nullable()
                    ->placeholder('Todos')->trueLabel('Resueltos')->falseLabel('Abiertos')->default(false),
            ])
            ->recordActions([
                Action::make('detalle')->label('Detalle')->icon('heroicon-o-eye')->color('gray')
                    ->modalHeading(fn (ErrorSistema $r) => "{$r->referencia} · {$r->clase_corta}")
                    ->modalSubmitAction(false)->modalCancelActionLabel('Cerrar')->modalWidth('5xl')
                    ->modalContent(fn (ErrorSistema $r) => new HtmlString(
                        '<div style="font-size:.85rem;display:grid;gap:.6rem">'
                        . '<div><b>Mensaje:</b> ' . e($r->mensaje) . '</div>'
                        . '<div><b>Dónde:</b> ' . e("{$r->archivo}:{$r->linea}") . '</div>'
                        . '<div><b>Petición:</b> ' . e(trim("{$r->metodo} {$r->url}")) . '</div>'
                        . '<div><b>Empresa / usuario:</b> ' . e(($r->empresa?->razon_social ?? '—') . ' / ' . ($r->usuario?->name ?? '—')) . '</div>'
                        . '<div><b>Ocurrencias:</b> ' . $r->ocurrencias . ' · primera ' . $r->primera_vez->timezone(config('app.timezone'))->format('d/m/Y h:i a') . ' · última ' . $r->ultima_vez->timezone(config('app.timezone'))->format('d/m/Y h:i a') . '</div>'
                        . '<pre style="white-space:pre-wrap;font-size:.72rem;background:rgba(148,163,184,.12);padding:.75rem;border-radius:.5rem;max-height:24rem;overflow:auto">' . e($r->traza) . '</pre>'
                        . '</div>')),
                Action::make('resolver')->label('Resuelto')->icon('heroicon-o-check')->color('success')
                    ->visible(fn (ErrorSistema $r) => ! $r->resuelto_at)
                    ->action(fn (ErrorSistema $r) => $r->forceFill(['resuelto_at' => now()])->save()),
            ])
            ->toolbarActions([
                BulkAction::make('resolver')->label('Marcar resueltos')->icon('heroicon-o-check')
                    ->action(fn (Collection $records) => ErrorSistema::query()->whereKey($records->modelKeys())->update(['resuelto_at' => now()])),
            ]);
    }
}
