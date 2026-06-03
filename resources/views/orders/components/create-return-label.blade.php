<div class="w-full flex flex-col gap-2">
    {{ $this->action }}

    @if($this->existingReturnLabel())
        <x-filament::button
            color="success"
            icon="heroicon-m-arrow-down-tray"
            wire:click="downloadReturnLabel"
            class="w-full"
        >
            Download retourlabel
        </x-filament::button>
    @endif

    <x-filament-actions::modals />
</div>
