<div class="space-y-3">
    @forelse($order->veloydOrders as $veloydOrder)
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex items-start justify-between gap-4">
                <div class="grid gap-2 space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        @if($veloydOrder->is_return)
                            <x-filament::badge color="warning" icon="heroicon-m-arrow-uturn-left">
                                Retourlabel
                            </x-filament::badge>
                        @endif
                        @if($veloydOrder->error && ! $veloydOrder->label_printed)
                            <x-filament::badge color="danger" icon="heroicon-m-x-circle">
                                Fout bij versturen
                            </x-filament::badge>
                        @elseif($veloydOrder->shipment_id && $veloydOrder->label_printed)
                            <x-filament::badge color="success" icon="heroicon-m-check-circle">
                                Label gedownload
                            </x-filament::badge>
                        @elseif($veloydOrder->shipment_id)
                            <x-filament::badge color="info" icon="heroicon-m-arrow-down-tray">
                                In wachtrij voor label download
                            </x-filament::badge>
                        @else
                            <x-filament::badge color="warning" icon="heroicon-m-clock">
                                Klaargezet
                            </x-filament::badge>
                        @endif
                        @php
                            $ds = [
                                'shipped' => ['Verzonden', 'info'],
                                'in_transit' => ['Onderweg', 'warning'],
                                'pickup' => ['Klaar voor afhalen', 'warning'],
                                'delivered' => ['Geleverd', 'success'],
                                'returned' => ['Retour', 'warning'],
                                'cancelled' => ['Geannuleerd', 'danger'],
                            ][$veloydOrder->status] ?? null;
                        @endphp
                        @if($ds)
                            <x-filament::badge :color="$ds[1]" icon="heroicon-m-truck">{{ $ds[0] }}</x-filament::badge>
                        @endif
                        @if($veloydOrder->is_return && $veloydOrder->is_label_email_sent)
                            <x-filament::badge color="success" icon="heroicon-m-envelope">
                                Mail verstuurd
                            </x-filament::badge>
                        @endif
                    </div>

                    <div class="space-y-1 text-sm">
                        @if($veloydOrder->shipment_id)
                            <p class="text-gray-950 dark:text-white">
                                <span class="font-medium">Shipment ID:</span>
                                {{ $veloydOrder->shipment_id }}
                            </p>
                        @endif

                        @if($veloydOrder->error)
                            <p class="whitespace-pre-wrap break-words text-danger-600 dark:text-danger-400">
                                <span class="font-medium">Foutmelding:</span>
                                {{ $veloydOrder->error }}
                            </p>
                        @elseif(! $veloydOrder->shipment_id)
                            <p class="text-gray-600 dark:text-gray-400">
                                Bestelling is klaargezet voor Veloyd. Download het label in het overzicht of pas hier
                                de waardes aan.
                            </p>
                        @endif
                    </div>

                    @php
                        $extraOptions = \Dashed\DashedEcommerceVeloyd\Classes\Veloyd::readOptionsForDisplay($veloydOrder->options);
                    @endphp
                    @if(count($extraOptions))
                        <div class="flex flex-wrap items-center gap-1.5">
                            @foreach($extraOptions as $opt)
                                <x-filament::badge color="gray">
                                    {{ $opt['label'] }}: {{ $opt['value'] }}
                                </x-filament::badge>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="flex items-center gap-1">
                    @php
                        $requeueTooltip = match(true) {
                            $veloydOrder->shipment_id && $veloydOrder->label_printed => 'Opnieuw in wachtrij zetten',
                            $veloydOrder->shipment_id => 'Staat al in wachtrij',
                            default => 'Concept nu aanmaken bij Veloyd',
                        };
                    @endphp

                    @if($veloydOrder->label_pdf_path && ! $veloydOrder->is_return)
                        <x-filament::icon-button
                            color="success"
                            icon="heroicon-m-arrow-down-tray"
                            size="sm"
                            tooltip="Download label PDF"
                            wire:click="downloadLabel({{ $veloydOrder->id }})"
                        />
                    @endif

                    @unless($veloydOrder->is_return)
                        <x-filament::icon-button
                            color="warning"
                            icon="heroicon-m-arrow-path"
                            size="sm"
                            :tooltip="$requeueTooltip"
                            wire:click="requeueVeloydOrder({{ $veloydOrder->id }})"
                        />
                    @endunless

                    <x-filament::icon-button
                        color="danger"
                        icon="heroicon-m-trash"
                        size="sm"
                        tooltip="Verwijder label"
                        wire:click="confirmDeleteVeloydOrder({{ $veloydOrder->id }})"
                    />
                </div>
            </div>
        </div>
    @empty
        <div class="rounded-xl border border-dashed border-gray-300 p-6 text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
            Er zijn nog geen Veloyd orders gekoppeld aan deze bestelling.
        </div>
    @endforelse

    <x-filament::modal
        id="delete-veloyd-order-modal"
        width="md"
        alignment="center"
        close-by-clicking-away="false"
    >
        <x-slot name="heading">
            Veloyd label verwijderen
        </x-slot>

        <x-slot name="description">
            Weet je zeker dat je dit Veloyd label wilt verwijderen? De gekoppelde track & trace wordt ook verwijderd.
        </x-slot>

        <x-slot name="footerActions">
            <x-filament::button
                color="gray"
                wire:click="closeDeleteModal"
            >
                Annuleren
            </x-filament::button>

            <x-filament::button
                color="danger"
                wire:click="deleteVeloydOrder"
            >
                Ja, verwijderen
            </x-filament::button>
        </x-slot>
    </x-filament::modal>
</div>
