<x-filament-panels::page>
    <form wire:submit="guardarPlan" class="grid gap-4">
        {{ $this->planForm }}
        @can('editar_rutinas_mantenimiento')
            <div><x-filament::button type="submit">Guardar plan</x-filament::button></div>
        @endcan
    </form>

    <form wire:submit="guardarPartida" class="grid gap-4">
        {{ $this->partidaForm }}
        @can('editar_rutinas_mantenimiento')
            <div class="flex flex-wrap gap-3">
                <x-filament::button type="submit">Guardar punto de partida</x-filament::button>
                <x-filament::button color="gray" wire:click="partidaHoy" type="button">Las que no tienen registro arrancan hoy</x-filament::button>
            </div>
        @endcan
    </form>
</x-filament-panels::page>
