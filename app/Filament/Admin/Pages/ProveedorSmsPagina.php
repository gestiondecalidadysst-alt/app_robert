<?php

namespace App\Filament\Admin\Pages;

use App\Models\ProveedorSms;
use App\Services\Mensajeria\OnurixSms;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use UnitEnum;

/**
 * Cuenta de SMS de la plataforma (Onurix): credenciales únicas para todas las empresas, costo real
 * por segmento y precio de venta por defecto. La plataforma paga a Onurix y le cobra a las empresas.
 */
class ProveedorSmsPagina extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.admin.proveedor-sms';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|UnitEnum|null $navigationGroup = 'Plataforma';

    protected static ?int $navigationSort = 25;

    protected static ?string $navigationLabel = 'Mensajes de texto';

    protected static ?string $title = 'SMS: cuenta y precios';

    protected static ?string $slug = 'proveedor-sms';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $p = ProveedorSms::actual();
        $this->form->fill([
            'activo' => $p->activo,
            'costo_segmento' => $p->costo_segmento,
            'precio_segmento' => $p->precio_segmento,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $p = ProveedorSms::actual();

        return $schema->statePath('data')->components([
            Section::make('Cuenta de Onurix (de la plataforma)')
                ->description('Una sola cuenta para todas las empresas. Las credenciales se guardan cifradas y nunca las ve ninguna empresa.')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Toggle::make('activo')->label('Servicio de SMS disponible')->inline(false)->columnSpanFull(),
                    TextInput::make('onurix_cliente')->label('Client ID')->autocomplete('off')->maxLength(50)
                        ->helperText(filled($p->onurix_cliente) ? 'Guardado. Déjelo vacío para conservarlo.' : 'Sin configurar.'),
                    TextInput::make('onurix_clave')->label('Clave del API')->password()->revealable()->autocomplete('new-password')->maxLength(200)
                        ->helperText(filled($p->onurix_clave) ? 'Guardada. Déjela vacía para conservarla.' : 'Sin configurar.'),
                ]),
            Section::make('Costo y precio por segmento')
                ->description('Onurix cobra por segmento (un SMS de más de 160 caracteres cuenta como varios). El costo solo lo ve el superadministrador; el precio es lo que paga cada empresa, salvo que tenga uno propio.')
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextInput::make('costo_segmento')->label('Lo que les cuesta un segmento (COP)')->numeric()->minValue(0)->required()->live(onBlur: true)
                        ->helperText('Lo que Onurix cobra a la plataforma.'),
                    TextInput::make('precio_segmento')->label('Precio de venta por segmento (COP)')->numeric()->minValue(0)->required()->live(onBlur: true)
                        ->helperText('Por defecto para las empresas.'),
                    TextInput::make('margen')->label('Margen por segmento')->disabled()->dehydrated(false)
                        ->prefix('$')
                        ->placeholder(fn (Get $get) => number_format(max(0, (int) $get('precio_segmento') - (int) $get('costo_segmento')), 0, ',', '.')),
                ]),
        ]);
    }

    public function guardar(): void
    {
        $d = $this->form->getState();
        $p = ProveedorSms::query()->first() ?? new ProveedorSms(['proveedor' => 'onurix']);
        $p->fill([
            'activo' => (bool) $d['activo'],
            'costo_segmento' => (int) $d['costo_segmento'],
            'precio_segmento' => (int) $d['precio_segmento'],
        ]);

        if (filled($d['onurix_cliente'] ?? null)) {
            $p->onurix_cliente = trim($d['onurix_cliente']);
        }
        if (filled($d['onurix_clave'] ?? null)) {
            $p->onurix_clave = trim($d['onurix_clave']);
        }

        if ($p->activo && (blank($p->onurix_cliente) || blank($p->onurix_clave))) {
            Notification::make()->danger()->title('Escriba las credenciales de Onurix para activar el servicio.')->send();

            return;
        }

        $p->save();
        $this->form->fill([...$d, 'onurix_cliente' => null, 'onurix_clave' => null]);
        Notification::make()->success()->title('Guardado')->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('saldo')->label('Ver saldo en Onurix')->icon('heroicon-o-wallet')->color('gray')
                ->action(function (): void {
                    $p = ProveedorSms::actual();
                    if (! $p->configurado()) {
                        Notification::make()->warning()->title('Configure y active la cuenta primero.')->send();

                        return;
                    }
                    $saldo = (new OnurixSms((string) $p->onurix_cliente, (string) $p->onurix_clave))->saldo();
                    $saldo === null
                        ? Notification::make()->danger()->title('No se pudo consultar el saldo en Onurix.')->send()
                        : Notification::make()->success()->title('Saldo en Onurix: $ '.number_format($saldo, 2, ',', '.'))->send();
                }),
            Action::make('consumo')->label('Ver consumo')->icon('heroicon-o-chart-bar')->color('gray')->url(ConsumoSms::getUrl()),
        ];
    }
}
