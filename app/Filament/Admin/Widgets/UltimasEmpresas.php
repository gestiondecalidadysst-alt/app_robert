<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\Empresas\EmpresaResource;
use App\Models\Empresa;
use Filament\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Últimas empresas dadas de alta en la plataforma, con acceso directo a su ficha.
 */
class UltimasEmpresas extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Últimas empresas';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Empresa::query()->withoutGlobalScopes()->withCount('usuarios')->latest('created_at'),
            )
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->columns([
                ImageColumn::make('logo_path')->label('')->disk('public')->imageSize(32)->circular(),
                TextColumn::make('razon_social')
                    ->label('Empresa')
                    ->weight('medium')
                    ->description(fn (Empresa $record) => $record->municipio?->nombre_completo)
                    ->searchable(),
                TextColumn::make('usuarios_count')->label('Usuarios')->badge(),
                IconColumn::make('activa')->label('Activa')->boolean(),
                TextColumn::make('created_at')->label('Alta')->date('d/m/Y')->sortable(),
            ])
            ->recordActions([
                Action::make('abrir')
                    ->label('Ficha')
                    ->icon('heroicon-m-pencil-square')
                    ->url(fn (Empresa $record) => EmpresaResource::getUrl('edit', ['record' => $record])),
                Action::make('entrar')
                    ->label('Entrar')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (Empresa $record) => url("/app/{$record->slug}")),
            ]);
    }
}
