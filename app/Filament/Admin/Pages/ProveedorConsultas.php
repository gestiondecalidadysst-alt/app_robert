<?php

namespace App\Filament\Admin\Pages;

use App\Enums\TipoConsultaTransito;
use App\Models\ProveedorTransito;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use UnitEnum;

/**
 * Proveedor de consultas de tránsito de la plataforma (PlacApi): API key de la plataforma, costo
 * real del crédito, créditos por tipo y PRECIO DE VENTA por tipo (lo que se cobra a las empresas).
 */
class ProveedorConsultas extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.admin.proveedor-consultas';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-magnifying-glass-circle';

    protected static string|UnitEnum|null $navigationGroup = 'Plataforma';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Consultas de tránsito';

    protected static ?string $title = 'Consultas de tránsito: proveedor y precios';

    public function getSubheading(): ?string
    {
        return 'Credenciales del proveedor de consultas de tránsito y el precio general por consulta que se cobra a las empresas.';
    }

    protected static ?string $slug = 'consultas-transito';

    public ?array $data = [];

    public function mount(): void
    {
        $p = ProveedorTransito::actual();
        $this->form->fill([
            'activo' => $p->activo,
            'url' => $p->url,
            'costo_credito' => (float) $p->costo_credito,
            'creditos' => collect(TipoConsultaTransito::cases())->mapWithKeys(fn ($t) => [$t->value => $p->creditosDe($t)])->all(),
            'precios' => collect(TipoConsultaTransito::cases())->mapWithKeys(fn ($t) => [$t->value => $p->precioDe($t)])->all(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $p = ProveedorTransito::actual();

        return $schema->statePath('data')->components([
            Section::make('Proveedor (PlacApi)')
                ->description('La cuenta es de la plataforma: las empresas consultan con ella y ustedes les cobran el precio de venta. La API key se guarda cifrada.')
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    Toggle::make('activo')->label('Consultas disponibles')->inline(false),
                    TextInput::make('url')->label('URL del API')->url()->required(),
                    TextInput::make('api_key')->label('API key')->password()->revealable()->autocomplete('new-password')
                        ->helperText(filled($p->api_key) ? 'Guardada: ••••'.mb_substr((string) $p->api_key, -4).'. Déjela vacía para conservarla.' : 'Sin configurar.'),
                    TextInput::make('costo_credito')->label('Lo que les cuesta un crédito (COP)')->numeric()->minValue(0)->required()
                        ->helperText('Solo lo ve el superadministrador. Para calcular el margen.'),
                ]),
            Section::make('Precio de venta por consulta')
                ->description('Precio fijo por tipo de consulta que se cobra a las empresas (se puede poner uno especial por empresa en Empresas → editar). Solo se cobra lo que el proveedor cobró: las consultas sin datos o repetidas en 24 horas no se cobran.')
                ->schema(collect(TipoConsultaTransito::cases())->map(fn (TipoConsultaTransito $t) => Grid::make(['default' => 1, 'md' => 4])->schema([
                    Text::make($t->getLabel())->weight('bold')->columnSpan(['md' => 1]),
                    TextInput::make("creditos.{$t->value}")->label('Créditos del proveedor')->numeric()->minValue(0)->required()->live(onBlur: true),
                    TextInput::make("precios.{$t->value}")->label('Precio de venta (COP)')->numeric()->minValue(0)->live(onBlur: true),
                    Text::make(fn (Get $get) => self::margen((float) $get('costo_credito'), (int) $get("creditos.{$t->value}"), $get("precios.{$t->value}")))->color('gray'),
                ]))->all()),
        ]);
    }

    private static function margen(float $costoCredito, int $creditos, mixed $precio): string
    {
        $costo = $costoCredito * $creditos;
        if ($precio === null || $precio === '') {
            return 'Costo $ '.number_format($costo, 0, ',', '.').' · sin precio de venta';
        }

        return 'Costo $ '.number_format($costo, 0, ',', '.').' · margen $ '.number_format((float) $precio - $costo, 0, ',', '.');
    }

    public function guardar(): void
    {
        $d = $this->form->getState();
        $p = ProveedorTransito::query()->first() ?? new ProveedorTransito(['proveedor' => 'placapi']);
        $p->fill([
            'activo' => (bool) $d['activo'],
            'url' => rtrim($d['url'], '/'),
            'costo_credito' => (float) $d['costo_credito'],
            'creditos' => array_map('intval', $d['creditos'] ?? []),
            'precios' => array_map(fn ($v) => $v === null || $v === '' ? null : (float) $v, $d['precios'] ?? []),
        ]);
        if (filled($d['api_key'] ?? null)) {
            $p->api_key = trim($d['api_key']);
        }
        if ($p->activo && blank($p->api_key)) {
            Notification::make()->danger()->title('Escriba la API key para activar las consultas.')->send();

            return;
        }
        $p->save();
        $this->form->fill([...$d, 'api_key' => null]);
        Notification::make()->success()->title('Guardado')->send();
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('consumo')->label('Ver consumo')->icon('heroicon-o-chart-bar')->color('gray')->url(ConsumoConsultas::getUrl())];
    }
}
