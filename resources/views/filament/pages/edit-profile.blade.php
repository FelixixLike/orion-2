<x-filament-panels::page>
    <form wire:submit="submit">
        {{ $this->form }}

        <div class="mt-4 flex justify-end gap-3">
            <x-filament::button color="gray" type="button" x-on:click="window.history.back()">
                Cancelar
            </x-filament::button>

            <x-filament::button type="submit">
                Guardar Cambios
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>