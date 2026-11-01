<?php

namespace App\Filament\App\Pages;

use App\Models\Empresa;
use App\Services\Mensajeria\Mensajero;
use App\Services\Mensajeria\OnurixSms;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use UnitEnum;

/**
 * Configuración → Integraciones: credenciales de SMS (Onurix) de la empresa y opciones de la
 * firma electrónica. Las credenciales se guardan cifradas y nunca se muestran completas.
 */
class Integraciones extends Page
{
    protected string $view = 'filament.app.pages.integraciones';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 70;

    protected static ?string $navigationLabel = 'Integraciones';

    protected static ?string $title = 'Integraciones';

    protected static ?string $slug = 'integraciones';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('ver_integraciones') ?? false;
    }

    private function empresa(): Empresa
    {
        return Empresa::findOrFail(Filament::getTenant()->getKey());
    }

    private function puedeEditar(): bool
    {
        return auth()->user()?->can('editar_integraciones') ?? false;
    }

    public function mount(): void
    {
        $e = $this->empresa();

        $this->form->fill([
            'sms_habilitado' => $e->sms_habilitado,
            'sms_limite_mensual' => $e->sms_limite_mensual,
            'firma_dias_enlace' => $e->firma_dias_enlace,
            'firma_dias_recordatorio' => $e->firma_dias_recordatorio,
            'firma_envio_automatico' => $e->firma_envio_automatico,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $e = $this->empresa();
        $oculto = fn (?string $valor) => filled($valor) ? 'Guardado: ••••' . mb_substr($valor, -4) . '. Déjelo vacío para conservarlo.' : 'Sin configurar.';

        return $schema
            ->statePath('data')
            ->disabled(! $this->puedeEditar())
            ->components([
                Section::make('Mensajes de texto (SMS) — Onurix')
                    ->description('Se usan para enviar el enlace de firma y el código de verificación. Cree su cuenta en onurix.com, copie aquí el Client ID y la clave del API, y autorice en Onurix la IP de este servidor (botón «Ver IP del servidor»).')
                    ->icon('heroicon-o-device-phone-mobile')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Toggle::make('sms_habilitado')->label('Enviar SMS')->inline(false)->columnSpanFull(),
                        TextInput::make('onurix_cliente')->label('Client ID')->maxLength(50)->autocomplete(false)
                            ->helperText($oculto($e->onurix_cliente)),
                        TextInput::make('onurix_clave')->label('Clave del API (key)')->password()->maxLength(200)->autocomplete('new-password')
                            ->helperText($oculto($e->onurix_clave)),
                        TextInput::make('sms_limite_mensual')->label('Límite de SMS al mes')->numeric()->minValue(1)
                            ->helperText('Opcional. Al llegar al límite, los mensajes se envían solo por correo.'),
                        Text::make('SMS enviados este mes: ' . Mensajero::smsDelMes($e))->color('gray'),
                    ]),
                Section::make('Firma electrónica de contratos')
                    ->icon('heroicon-o-pencil-square')
                    ->columns(['default' => 1, 'md' => 3])
                    ->schema([
                        TextInput::make('firma_dias_enlace')->label('El enlace vence a los (días)')->numeric()->minValue(1)->maxValue(30)->required(),
                        TextInput::make('firma_dias_recordatorio')->label('Recordar si no firma en (días)')->numeric()->minValue(0)->maxValue(15)->required()
                            ->helperText('0 = no enviar recordatorio.'),
                        Toggle::make('firma_envio_automatico')->label('Enviar para firma al emitir')->inline(false)
                            ->helperText('Si está apagado, se envía con el botón «Enviar para firma» después de revisar los datos.'),
                    ]),
            ]);
    }

    public function guardar(): void
    {
        abort_unless($this->puedeEditar(), 403);

        $datos = $this->form->getState();
        $empresa = $this->empresa();
        $cambiaronCredenciales = filled($datos['onurix_cliente'] ?? null) || filled($datos['onurix_clave'] ?? null);

        $empresa->fill([
            'sms_habilitado' => (bool) $datos['sms_habilitado'],
            'sms_limite_mensual' => filled($datos['sms_limite_mensual'] ?? null) ? (int) $datos['sms_limite_mensual'] : null,
            'firma_dias_enlace' => (int) $datos['firma_dias_enlace'],
            'firma_dias_recordatorio' => (int) $datos['firma_dias_recordatorio'],
            'firma_envio_automatico' => (bool) $datos['firma_envio_automatico'],
        ]);

        if (filled($datos['onurix_cliente'] ?? null)) {
            $empresa->onurix_cliente = trim($datos['onurix_cliente']);
        }
        if (filled($datos['onurix_clave'] ?? null)) {
            $empresa->onurix_clave = trim($datos['onurix_clave']);
        }

        if ($empresa->sms_habilitado && (blank($empresa->onurix_cliente) || blank($empresa->onurix_clave))) {
            Notification::make()->danger()->title('Faltan las credenciales de Onurix para activar los SMS.')->send();

            return;
        }

        $empresa->save();

        if ($cambiaronCredenciales) {
            // La auditoría registra quién cambió las credenciales, nunca su valor.
            activity('Empresa')->performedOn($empresa)->causedBy(auth()->user())
                ->tap(fn ($a) => $a->empresa_id = $empresa->id)
                ->log('Actualizó las credenciales de SMS (Onurix)');
        }

        $this->form->fill([...$datos, 'onurix_cliente' => null, 'onurix_clave' => null]);

        Notification::make()->success()->title('Integraciones guardadas')->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ip')
                ->label('Ver IP del servidor')
                ->icon('heroicon-o-globe-alt')
                ->color('gray')
                ->action(function (): void {
                    $ip = OnurixSms::ipSalida();

                    $ip === null
                        ? Notification::make()->danger()->title('No se pudo averiguar la IP del servidor')->send()
                        : Notification::make()->info()->title("IP del servidor: {$ip}")
                            ->body('Regístrela en su cuenta de Onurix como IP permitida para el API. Si el proveedor del servidor cambia la IP, hay que actualizarla allá.')
                            ->persistent()->send();
                }),
            Action::make('saldo')
                ->label('Consultar saldo')
                ->icon('heroicon-o-banknotes')
                ->color('gray')
                ->visible(fn () => $this->empresa()->smsConfigurado())
                ->action(function (): void {
                    $e = $this->empresa();
                    $saldo = (new OnurixSms((string) $e->onurix_cliente, (string) $e->onurix_clave))->saldo();

                    $saldo === null
                        ? Notification::make()->danger()->title('No se pudo consultar el saldo')->body('Revise las credenciales.')->send()
                        : Notification::make()->success()->title('Saldo en Onurix: ' . number_format($saldo, 0, ',', '.'))->send();
                }),
            Action::make('prueba')
                ->label('Enviar SMS de prueba')
                ->icon('heroicon-o-paper-airplane')
                ->visible(fn () => $this->empresa()->smsConfigurado() && $this->puedeEditar())
                ->schema([TextInput::make('celular')->label('Celular')->tel()->required()->helperText('Le llegará un SMS de prueba (consume saldo).')])
                ->action(function (array $data): void {
                    $e = $this->empresa();
                    $envio = Mensajero::sms($e, 'prueba', $data['celular'],
                        ($e->nombre_comercial ?: $e->razon_social) . ': mensaje de prueba de Ruta. La configuracion de SMS funciona.', ahora: true);

                    $envio->estado === 'enviado'
                        ? Notification::make()->success()->title('SMS enviado')->send()
                        : Notification::make()->danger()->title('No se envió el SMS')->body($envio->error)->persistent()->send();
                }),
        ];
    }
}
