<x-filament-panels::page>
    <form wire:submit="guardar" class="grid gap-6">
        {{ $this->form }}

        @can('editar_integraciones')
            <div>
                <x-filament::button type="submit">Guardar</x-filament::button>
            </div>
        @endcan
    </form>
</x-filament-panels::page>
