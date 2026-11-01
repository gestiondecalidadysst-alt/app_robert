<x-filament-panels::page>
    <form wire:submit="guardar" class="grid gap-6">
        {{ $this->form }}
        <div>
            <x-filament::button type="submit">Registrar inspección</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
