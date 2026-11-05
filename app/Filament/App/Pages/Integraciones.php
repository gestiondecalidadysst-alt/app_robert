<?php

namespace App\Filament\App\Pages;

use App\Enums\ModuloEmpresa;
use App\Enums\TipoConsultaTransito;
use App\Models\Empresa;
use App\Models\PagoContrato;
use App\Models\WebhookEntrega;
use App\Services\Mensajeria\Mensajero;
use App\Services\Mensajeria\OnurixSms;
use App\Support\Webhooks;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
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
        $this->form->fill($this->valores($this->empresa()));
    }

    /** Valores actuales (también para conservar los de secciones ocultas por módulos no contratados). */
    private function valores(Empresa $e): array
    {
        return [
            'sms_habilitado' => $e->sms_habilitado,
            'sms_limite_mensual' => $e->sms_limite_mensual,
            'firma_dias_enlace' => $e->firma_dias_enlace,
            'firma_dias_recordatorio' => $e->firma_dias_recordatorio,
            'firma_envio_automatico' => $e->firma_envio_automatico,
            'webhook_activo' => $e->webhook_activo,
            'webhook_eventos' => $e->webhook_eventos ?? array_keys(Webhooks::EVENTOS),
            'km_diarios_defecto' => $e->km_diarios_defecto,
            'mantenimiento_dias_aviso' => $e->mantenimiento_dias_aviso,
            'recibo_prefijo' => $e->recibo_prefijo,
            'jornada_max_horas_dia' => $e->jornada_max_horas_dia,
            'jornada_max_horas_continuas' => $e->jornada_max_horas_continuas,
            'jornada_descanso_horas' => $e->jornada_descanso_horas,
            'planeacion_antelacion_horas' => $e->planeacion_antelacion_horas,
            'recibo_numero_inicial' => $e->recibo_numero_inicial,
            'transito_tipos' => $e->transito_tipos ?? [],
            'transito_auto' => $e->transito_auto,
            'transito_auto_tipos' => $e->transito_auto_tipos ?? [],
            'transito_frecuencia_dias' => $e->transito_frecuencia_dias,
            'transito_tope_mensual' => $e->transito_tope_mensual,
            'transito_bloquear_licencia' => $e->transito_bloquear_licencia,
            'transito_bloquear_comparendos' => $e->transito_bloquear_comparendos,
            'transito_bloquear_desde' => $e->transito_bloquear_desde,
            'carga_rol' => $e->carga_rol,
            'rndc_ambiente' => $e->rndc_ambiente,
            'rndc_conectado' => $e->rndc_conectado,
        ];
    }

    public function form(Schema $schema): Schema
    {
        $e = $this->empresa();
        $oculto = fn (?string $valor) => filled($valor) ? 'Guardado: ••••'.mb_substr($valor, -4).'. Déjelo vacío para conservarlo.' : 'Sin configurar.';

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
                        Text::make('SMS enviados este mes: '.Mensajero::smsDelMes($e))->color('gray'),
                    ]),
                Section::make('Avisos por webhook (n8n)')
                    ->description('El sistema envía un POST JSON a esta URL en cada evento elegido (por ejemplo, un nodo Webhook de n8n para reenviar por WhatsApp, correo o Telegram). Cada aviso va firmado con HMAC-SHA256 en la cabecera X-Ruta-Firma.')
                    ->icon('heroicon-o-bolt')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Toggle::make('webhook_activo')->label('Enviar avisos')->inline(false)->columnSpanFull(),
                        TextInput::make('webhook_url')->label('URL del webhook')->url()->maxLength(500)->autocomplete(false)
                            ->placeholder('https://n8n.su-dominio.com/webhook/ruta')
                            ->helperText(fn () => filled($e->webhook_url) ? 'Guardada: '.self::ocultarUrl($e->webhook_url).'. Déjela vacía para conservarla.' : 'Sin configurar.'),
                        TextInput::make('webhook_secreto')->label('Secreto para la firma')->password()->revealable()->maxLength(200)->autocomplete('new-password')
                            ->helperText(fn () => filled($e->webhook_secreto) ? 'Guardado. Déjelo vacío para conservarlo.' : 'Opcional pero recomendado: n8n puede verificar que el aviso viene de Ruta.'),
                        CheckboxList::make('webhook_eventos')->label('Eventos')->options(Webhooks::EVENTOS)->columns(2)->columnSpanFull(),
                    ]),
                Section::make('Mantenimiento')
                    ->visible($e->tieneModulo(ModuloEmpresa::Mantenimiento))
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextInput::make('km_diarios_defecto')->label('Km por día por defecto')->numeric()->minValue(1)->maxValue(2000)->required()
                            ->helperText('Para estimar fechas de vehículos sin lecturas de kilometraje suficientes.'),
                        TextInput::make('mantenimiento_dias_aviso')->label('Avisar actividades con (días) de anticipación')->numeric()->minValue(1)->maxValue(60)->required(),
                    ]),
                Section::make('Planeador de servicios (jornada y fatiga)')
                    ->visible($e->tieneModulo(ModuloEmpresa::Pasajeros))
                    ->description('Política de la empresa para el control de jornada del PESV (paso 8, prevención de la fatiga). Superarla solo genera avisos en el planeador; no impide planificar ni expedir.')
                    ->icon('heroicon-o-clock')
                    ->columns(['default' => 1, 'md' => 4])
                    ->schema([
                        TextInput::make('jornada_max_horas_dia')->label('Conducción máxima al día (h)')->numeric()->minValue(1)->maxValue(24)->required(),
                        TextInput::make('jornada_max_horas_continuas')->label('Conducción continua máxima (h)')->numeric()->minValue(1)->maxValue(24)->required()
                            ->helperText('Más que esto sin parada segura en la ruta genera aviso.'),
                        TextInput::make('jornada_descanso_horas')->label('Descanso mínimo entre jornadas (h)')->numeric()->minValue(1)->maxValue(24)->required(),
                        TextInput::make('planeacion_antelacion_horas')->label('Planificar con (h) de antelación')->numeric()->minValue(0)->maxValue(720)->required()
                            ->helperText('Para el indicador de planificación.'),
                    ]),
                Section::make('Recibos de caja')
                    ->visible($e->tieneModulo(ModuloEmpresa::Pasajeros))
                    ->description('Cada pago de un contrato genera un recibo con número consecutivo para entregar al cliente.')
                    ->icon('heroicon-o-receipt-percent')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextInput::make('recibo_prefijo')->label('Prefijo')->maxLength(10)->alphaDash()->placeholder('RC')
                            ->helperText('Aparece antes del número: RC-0001. Puede dejarlo vacío.'),
                        TextInput::make('recibo_numero_inicial')->label('Empezar en el número')->numeric()->minValue(1)->required()
                            ->helperText(function () use ($e) {
                                $ultimo = (int) PagoContrato::query()->deEmpresa($e)->max('numero');

                                return 'Para continuar su talonario actual. '.($ultimo ? "Último recibo emitido: {$ultimo}." : 'Aún no hay recibos.').' Nunca se repite un número.';
                            }),
                    ]),
                self::seccionRndc($e, $oculto),
                self::seccionTransito($e),
                Section::make('Firma electrónica de contratos')
                    ->visible($e->tieneModulo(ModuloEmpresa::Pasajeros))
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

    /** RNDC (módulo de carga): credenciales del web service del Ministerio, cifradas. */
    private static function seccionRndc(Empresa $e, \Closure $oculto): Section
    {
        return Section::make('RNDC – Registro Nacional de Despachos de Carga')
            ->description('Usuario delegado del RNDC de la empresa (lo crea el representante legal en el RNDC). Mientras no se active la conexión, los registros se hacen en la web del RNDC con la ficha en PDF y aquí se anota el número de radicado.')
            ->icon('heroicon-o-cloud-arrow-up')
            ->visible($e->tieneModulo(ModuloEmpresa::Carga))
            ->columns(['default' => 1, 'md' => 3])
            ->schema([
                Select::make('carga_rol')->label('La empresa es')->required()
                    ->options(['transportadora' => 'Empresa de transporte habilitada', 'generador' => 'Generador de carga', 'ambos' => 'Ambos']),
                TextInput::make('rndc_usuario')->label('Usuario del RNDC')->maxLength(60)->autocomplete(false)->helperText($oculto($e->rndc_usuario)),
                TextInput::make('rndc_clave')->label('Clave del RNDC')->password()->maxLength(100)->autocomplete('new-password')->helperText($oculto($e->rndc_clave)),
                Select::make('rndc_ambiente')->label('Ambiente')->required()
                    ->options(['pruebas' => 'Pruebas', 'produccion' => 'Producción'])
                    ->helperText('Use pruebas hasta confirmar con el Ministerio que todo se registra bien.'),
                Toggle::make('rndc_conectado')->label('Conexión por web service activa')->inline(false)
                    ->helperText('Encendida, el botón «Registrar en el RNDC» envía los datos directamente.'),
            ]);
    }

    /** Consultas de tránsito: solo si el superadministrador habilitó el módulo para la empresa. */
    private static function seccionTransito(Empresa $e): Section
    {
        $tipos = collect(TipoConsultaTransito::cases());
        $precios = $tipos->map(fn (TipoConsultaTransito $t) => $t->getLabel().': '.(($v = $e->precioTransito($t)) !== null ? '$ '.number_format($v, 0, ',', '.') : 'sin precio'))->implode(' · ');
        $gasto = $e->gastoTransitoDelMes();

        return Section::make('Consultas de tránsito (comparendos y RUNT)')
            ->description('Cada consulta tiene costo y se cobra en la factura de la plataforma. Solo se cobran las que traen datos; si el sistema de tránsito no tiene información, no se cobra. Ninguna consulta se hace sola a menos que active las automáticas.')
            ->icon('heroicon-o-magnifying-glass-circle')
            ->visible($e->transito_habilitado)
            ->columns(['default' => 1, 'md' => 3])
            ->schema([
                Text::make('Precio por consulta — '.$precios)->columnSpanFull(),
                Text::make('Gastado este mes: $ '.number_format($gasto, 0, ',', '.').($e->transito_tope_mensual ? ' de $ '.number_format((float) $e->transito_tope_mensual, 0, ',', '.').' (tope)' : ' (sin tope)'))->color('gray')->columnSpanFull(),
                CheckboxList::make('transito_tipos')->label('Consultas que usa la empresa')->options($tipos->mapWithKeys(fn ($t) => [$t->value => $t->getLabel()])->all())->columns(2)->columnSpanFull()
                    ->helperText('Las que no marque no aparecen en conductores ni vehículos.'),
                TextInput::make('transito_tope_mensual')->label('Tope de gasto al mes')->numeric()->minValue(0)->prefix('$')
                    ->helperText('Al llegar al tope se bloquean todas las consultas hasta el mes siguiente. Vacío = sin tope.'),
                Toggle::make('transito_auto')->label('Consultas automáticas')->inline(false)->live()
                    ->helperText('Solo a los conductores y vehículos que marque con «Incluir en consultas automáticas».'),
                TextInput::make('transito_frecuencia_dias')->label('Repetir cada (días)')->numeric()->minValue(1)->maxValue(365)->required()
                    ->visible(fn ($get) => (bool) $get('transito_auto')),
                CheckboxList::make('transito_auto_tipos')->label('Consultas automáticas a realizar')->options($tipos->mapWithKeys(fn ($t) => [$t->value => $t->getLabel()])->all())->columns(2)->columnSpanFull()
                    ->visible(fn ($get) => (bool) $get('transito_auto')),
                Toggle::make('transito_bloquear_licencia')->label('Bloquear extractos si la licencia está suspendida')->inline(false),
                Toggle::make('transito_bloquear_comparendos')->label('Bloquear extractos si el conductor tiene comparendos pendientes')->inline(false)->live(),
                TextInput::make('transito_bloquear_desde')->label('Solo si la deuda es desde')->numeric()->minValue(0)->prefix('$')
                    ->visible(fn ($get) => (bool) $get('transito_bloquear_comparendos'))->helperText('Vacío = cualquier valor.'),
            ]);
    }

    public static function ocultarUrl(string $url): string
    {
        $p = parse_url($url);

        return ($p['scheme'] ?? 'https').'://'.($p['host'] ?? '').(isset($p['path']) ? '/…'.substr($p['path'], -6) : '');
    }

    /** Últimos avisos por webhook (para la vista). */
    public function getEntregas()
    {
        return WebhookEntrega::query()->latest('id')->limit(10)->get();
    }

    public function guardar(): void
    {
        abort_unless($this->puedeEditar(), 403);

        $empresa = $this->empresa();
        $datos = [...$this->valores($empresa), ...$this->form->getState()];
        $cambiaronCredenciales = filled($datos['onurix_cliente'] ?? null) || filled($datos['onurix_clave'] ?? null);
        $cambioRndc = filled($datos['rndc_usuario'] ?? null) || filled($datos['rndc_clave'] ?? null);

        $empresa->fill([
            'sms_habilitado' => (bool) $datos['sms_habilitado'],
            'sms_limite_mensual' => filled($datos['sms_limite_mensual'] ?? null) ? (int) $datos['sms_limite_mensual'] : null,
            'firma_dias_enlace' => (int) $datos['firma_dias_enlace'],
            'firma_dias_recordatorio' => (int) $datos['firma_dias_recordatorio'],
            'firma_envio_automatico' => (bool) $datos['firma_envio_automatico'],
            'webhook_activo' => (bool) ($datos['webhook_activo'] ?? false),
            'webhook_eventos' => array_values($datos['webhook_eventos'] ?? []),
            'km_diarios_defecto' => (int) $datos['km_diarios_defecto'],
            'mantenimiento_dias_aviso' => (int) $datos['mantenimiento_dias_aviso'],
            'recibo_prefijo' => mb_strtoupper(trim((string) ($datos['recibo_prefijo'] ?? ''))),
            'recibo_numero_inicial' => max(1, (int) $datos['recibo_numero_inicial']),
            'jornada_max_horas_dia' => (int) $datos['jornada_max_horas_dia'],
            'jornada_max_horas_continuas' => (int) $datos['jornada_max_horas_continuas'],
            'jornada_descanso_horas' => (int) $datos['jornada_descanso_horas'],
            'planeacion_antelacion_horas' => (int) $datos['planeacion_antelacion_horas'],
        ]);
        if ($empresa->tieneModulo(ModuloEmpresa::Carga)) {
            $empresa->fill([
                'carga_rol' => $datos['carga_rol'] ?? 'transportadora',
                'rndc_ambiente' => $datos['rndc_ambiente'] ?? 'pruebas',
                'rndc_conectado' => (bool) ($datos['rndc_conectado'] ?? false),
            ]);
            if (filled($datos['rndc_usuario'] ?? null)) {
                $empresa->rndc_usuario = trim($datos['rndc_usuario']);
            }
            if (filled($datos['rndc_clave'] ?? null)) {
                $empresa->rndc_clave = trim($datos['rndc_clave']);
            }
            if ($empresa->rndc_conectado && (blank($empresa->rndc_usuario) || blank($empresa->rndc_clave))) {
                Notification::make()->danger()->title('Escriba el usuario y la clave del RNDC para activar la conexión.')->send();

                return;
            }
        }
        if ($empresa->transito_habilitado) {
            $empresa->fill([
                'transito_tipos' => array_values($datos['transito_tipos'] ?? []),
                'transito_auto' => (bool) ($datos['transito_auto'] ?? false),
                'transito_auto_tipos' => array_values($datos['transito_auto_tipos'] ?? []),
                'transito_frecuencia_dias' => max(1, (int) ($datos['transito_frecuencia_dias'] ?? 30)),
                'transito_tope_mensual' => filled($datos['transito_tope_mensual'] ?? null) ? (float) $datos['transito_tope_mensual'] : null,
                'transito_bloquear_licencia' => (bool) ($datos['transito_bloquear_licencia'] ?? false),
                'transito_bloquear_comparendos' => (bool) ($datos['transito_bloquear_comparendos'] ?? false),
                'transito_bloquear_desde' => filled($datos['transito_bloquear_desde'] ?? null) ? (float) $datos['transito_bloquear_desde'] : null,
            ]);
        }
        if (filled($datos['webhook_url'] ?? null)) {
            $empresa->webhook_url = trim($datos['webhook_url']);
        }
        if (filled($datos['webhook_secreto'] ?? null)) {
            $empresa->webhook_secreto = trim($datos['webhook_secreto']);
        }
        if ($empresa->webhook_activo && blank($empresa->webhook_url)) {
            Notification::make()->danger()->title('Escriba la URL del webhook para activar los avisos.')->send();

            return;
        }

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

        if ($cambioRndc) {
            activity('Empresa')->performedOn($empresa)->causedBy(auth()->user())
                ->tap(fn ($a) => $a->empresa_id = $empresa->id)
                ->log('Actualizó las credenciales del RNDC');
        }

        if ($cambiaronCredenciales) {
            // La auditoría registra quién cambió las credenciales, nunca su valor.
            activity('Empresa')->performedOn($empresa)->causedBy(auth()->user())
                ->tap(fn ($a) => $a->empresa_id = $empresa->id)
                ->log('Actualizó las credenciales de SMS (Onurix)');
        }

        $this->form->fill([...$datos, 'onurix_cliente' => null, 'onurix_clave' => null, 'webhook_url' => null, 'webhook_secreto' => null, 'rndc_usuario' => null, 'rndc_clave' => null]);

        Notification::make()->success()->title('Integraciones guardadas')->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('webhook')
                ->label('Probar webhook')
                ->icon('heroicon-o-bolt')
                ->color('gray')
                ->visible(fn () => $this->empresa()->webhookConfigurado() && $this->puedeEditar())
                ->action(function (): void {
                    $entrega = Webhooks::emitir($this->empresa(), 'prueba', ['mensaje' => 'Prueba de conexión desde Ruta', 'usuario' => auth()->user()->name]);
                    Notification::make()->success()->title('Aviso de prueba en cola')
                        ->body('Revise el resultado en «Últimos avisos enviados» en unos segundos'.($entrega ? " (entrega #{$entrega->id})." : '.'))->send();
                }),
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
                        : Notification::make()->success()->title('Saldo en Onurix: '.number_format($saldo, 0, ',', '.'))->send();
                }),
            Action::make('prueba')
                ->label('Enviar SMS de prueba')
                ->icon('heroicon-o-paper-airplane')
                ->visible(fn () => $this->empresa()->smsConfigurado() && $this->puedeEditar())
                ->schema([TextInput::make('celular')->label('Celular')->tel()->required()->helperText('Le llegará un SMS de prueba (consume saldo).')])
                ->action(function (array $data): void {
                    $e = $this->empresa();
                    $envio = Mensajero::sms($e, 'prueba', $data['celular'],
                        ($e->nombre_comercial ?: $e->razon_social).': mensaje de prueba de Ruta. La configuracion de SMS funciona.', ahora: true);

                    $envio->estado === 'enviado'
                        ? Notification::make()->success()->title('SMS enviado')->send()
                        : Notification::make()->danger()->title('No se envió el SMS')->body($envio->error)->persistent()->send();
                }),
        ];
    }
}
