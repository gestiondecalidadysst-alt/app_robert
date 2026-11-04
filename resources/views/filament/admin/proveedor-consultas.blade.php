<x-filament-panels::page>
    <form wire:submit="guardar" style="display: grid; gap: 1.5rem">
        {{ $this->form }}
        <div><x-filament::button type="submit">Guardar</x-filament::button></div>
    </form>
</x-filament-panels::page>
