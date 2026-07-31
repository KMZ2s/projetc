<x-filament-panels::page>
    <form wire:submit.prevent="save">
        {{ $this->form }}
        <div class="flex justify-end mt-4">
            <x-filament::button type="submit">
                Salvar
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>