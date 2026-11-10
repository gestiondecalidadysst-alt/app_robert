<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\Manual\SeccionManualResource;
use App\Models\SeccionManual;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;

/**
 * Manual de la aplicación (lectura): índice por grupos, buscador y descarga en PDF.
 * El contenido se edita en el recurso «Secciones del manual». Solo superadministrador.
 */
class Manual extends Page
{
    protected string $view = 'filament.admin.manual';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Manual';

    protected static ?string $title = 'Manual de la plataforma';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'manual';

    public function getSubheading(): ?string
    {
        return 'Qué hace cada módulo y cómo se usa en el día a día. Usa el buscador para ir directo a un tema.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdf')
                ->label('Descargar PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(fn () => route('admin.manual.pdf'))
                ->openUrlInNewTab(),
            Action::make('gestionar')
                ->label('Editar manual')
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->url(fn () => SeccionManualResource::getUrl('index')),
            Action::make('nueva')
                ->label('Nueva sección')
                ->icon('heroicon-o-plus')
                ->url(fn () => SeccionManualResource::getUrl('create')),
        ];
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $grupos = SeccionManual::query()->ordenado()->get()->groupBy('grupo');

        return [
            'grupos' => $grupos,
            'editarUrl' => fn (SeccionManual $s) => SeccionManualResource::getUrl('edit', ['record' => $s]),
        ];
    }
}
