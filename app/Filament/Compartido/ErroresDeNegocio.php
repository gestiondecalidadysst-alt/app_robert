<?php

namespace App\Filament\Compartido;

use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Ejecuta una regla de negocio desde una acción de Filament. Si la regla rechaza la operación
 * (ValidationException), muestra el mensaje como notificación y deja el modal abierto.
 */
final class ErroresDeNegocio
{
    public static function intentar(Action $action, Closure $operacion): mixed
    {
        try {
            return $operacion();
        } catch (ValidationException $e) {
            Notification::make()
                ->title('No se pudo completar')
                ->body(collect($e->errors())->flatten()->implode(' '))
                ->danger()
                ->persistent()
                ->send();

            $action->halt();
        }

        return null;
    }
}
