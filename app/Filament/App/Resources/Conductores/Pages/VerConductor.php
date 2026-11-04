<?php

namespace App\Filament\App\Resources\Conductores\Pages;

use App\Filament\App\Resources\Conductores\Acciones\CambiarEstadoConductorAction;
use App\Filament\App\Resources\Conductores\ConductorResource;
use App\Actions\Pesv\CrearAccesoConductor;
use App\Enums\EstadoConductor;
use App\Filament\Compartido\ErroresDeNegocio;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;

/**
 * Hoja de vida del conductor.
 */
class VerConductor extends ViewRecord
{
    protected static string $resource = ConductorResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->nombre_completo;
    }

    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->load(self::RELACIONES);
    }

    private const RELACIONES = [
        'tipoIdentificacion', 'municipio.departamento', 'nivelAcademico', 'oficina', 'eps', 'arl',
        'documentos.categoriaLicencia', 'asignacionesVigentes.vehiculo', 'usuario',
    ];

    protected function getHeaderActions(): array
    {
        return [
            \App\Filament\App\Carga\AccionesRndc::grupo(),
            Action::make('portal')
                ->label(fn () => $this->getRecord()->user_id ? 'Restablecer clave del portal' : 'Acceso al portal')
                ->icon('heroicon-o-device-phone-mobile')
                ->color('gray')
                ->visible(fn () => auth()->user()?->can('crear_portal_conductor') && $this->getRecord()->estado !== EstadoConductor::Retirado)
                ->requiresConfirmation()
                ->modalHeading(fn () => $this->getRecord()->user_id ? 'Restablecer la clave del portal' : 'Dar acceso al portal del conductor')
                ->modalDescription(fn () => 'Se generará una clave temporal para ' . $this->getRecord()->nombre_completo
                    . '. Ingresará con su documento ' . $this->getRecord()->documento . ' y deberá cambiarla al entrar. '
                    . ($this->getRecord()->user_id ? 'La clave anterior deja de funcionar.' : ''))
                ->modalSubmitActionLabel('Generar clave')
                ->action(function (Action $action): void {
                    $resultado = ErroresDeNegocio::intentar($action, fn () => app(CrearAccesoConductor::class)($this->getRecord()));
                    $this->refrescar();

                    Notification::make()
                        ->success()
                        ->title('Clave temporal: ' . $resultado['clave'])
                        ->body('Entréguesela al conductor (se muestra una sola vez). Ingresa en ' . route('conductor.ingresar')
                            . ' con su documento ' . $this->getRecord()->documento . '.')
                        ->persistent()
                        ->send();
                }),
            CambiarEstadoConductorAction::make()->after(fn () => $this->refrescar()),
            EditAction::make(),
        ];
    }

    /** Documentos, asignaciones o estado cambiaron: recarga el resumen. */
    #[On('documentos-actualizados')]
    public function refrescar(): void
    {
        $this->getRecord()->refresh()->load(self::RELACIONES);
    }

    public function getContentTabLabel(): ?string
    {
        return 'Resumen';
    }
}
