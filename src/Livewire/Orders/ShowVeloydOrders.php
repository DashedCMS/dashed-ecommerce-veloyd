<?php

namespace Dashed\DashedEcommerceVeloyd\Livewire\Orders;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Symfony\Component\HttpFoundation\Response;
use Dashed\DashedEcommerceCore\Models\OrderTrackAndTrace;
use Dashed\DashedEcommerceVeloyd\Jobs\CreateVeloydConceptOrdersJob;

class ShowVeloydOrders extends Component
{
    public $order;

    public bool $showDeleteModal = false;
    public ?int $veloydOrderIdToDelete = null;

    public function mount($order): void
    {
        $this->order = $order;
    }

    public function confirmDeleteVeloydOrder(int $veloydOrderId): void
    {
        $this->veloydOrderIdToDelete = $veloydOrderId;
        $this->showDeleteModal = true;

        $this->dispatch('open-modal', id: 'delete-veloyd-order-modal');
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->veloydOrderIdToDelete = null;

        $this->dispatch('close-modal', id: 'delete-veloyd-order-modal');
    }

    public function requeueVeloydOrder(int $veloydOrderId): void
    {
        $veloydOrder = $this->order->veloydOrders()
            ->where('id', $veloydOrderId)
            ->first();

        if (! $veloydOrder) {
            Notification::make()
                ->title('Veloyd order niet gevonden')
                ->danger()
                ->send();

            return;
        }

        if (! $veloydOrder->shipment_id) {
            $veloydOrder->error = null;
            $veloydOrder->save();

            CreateVeloydConceptOrdersJob::dispatch()->onQueue('ecommerce');

            $this->order->refresh();

            Notification::make()
                ->title('Concept wordt aangemaakt bij Veloyd')
                ->body('De job is gestart - ververs deze pagina na een paar seconden om de status bij te werken.')
                ->success()
                ->send();

            return;
        }

        if (! $veloydOrder->label_printed) {
            Notification::make()
                ->title('Label staat al in de wachtrij')
                ->body('Dit label wordt bij de volgende download in het overzicht meegenomen.')
                ->warning()
                ->send();

            return;
        }

        $veloydOrder->label_printed = false;
        $veloydOrder->save();

        $this->order->refresh();

        Notification::make()
            ->title('Label opnieuw in de wachtrij gezet')
            ->body('Het label wordt bij de volgende download in het overzicht opnieuw meegedownload.')
            ->success()
            ->send();
    }

    public function deleteVeloydOrder(): void
    {
        if (! $this->veloydOrderIdToDelete) {
            return;
        }

        $veloydOrder = $this->order->veloydOrders()
            ->where('id', $this->veloydOrderIdToDelete)
            ->first();

        if (! $veloydOrder) {
            $this->closeDeleteModal();

            Notification::make()
                ->title('Veloyd order niet gevonden')
                ->danger()
                ->send();

            return;
        }

        DB::transaction(function () use ($veloydOrder) {
            if (! empty($veloydOrder->track_and_trace[0])) {
                OrderTrackAndTrace::where('order_id', $this->order->id)
                    ->where('code', array_key_first($veloydOrder->track_and_trace[0]))
                    ->delete();
            }

            $veloydOrder->delete();
        });

        $this->order->refresh();

        $this->closeDeleteModal();

        Notification::make()
            ->title('Veloyd label verwijderd')
            ->body('De gekoppelde track & trace is ook verwijderd.')
            ->success()
            ->send();
    }

    public function downloadLabel(int $veloydOrderId): ?Response
    {
        $veloydOrder = $this->order->veloydOrders()
            ->where('id', $veloydOrderId)
            ->first();

        if (! $veloydOrder || ! $veloydOrder->label_pdf_path) {
            Notification::make()
                ->title('Label niet gevonden')
                ->body('Er staat geen PDF klaar voor dit label.')
                ->danger()
                ->send();

            return null;
        }

        if ($veloydOrder->is_return) {
            Notification::make()
                ->title('Retourlabel niet downloadbaar in de lijst')
                ->body('Download een retourlabel via de knop "Download retourlabel" of mail het naar de klant.')
                ->danger()
                ->send();

            return null;
        }

        if (! Storage::disk('public')->exists($veloydOrder->label_pdf_path)) {
            Notification::make()
                ->title('Label-bestand ontbreekt')
                ->body('Het PDF-bestand is niet meer aanwezig op de server. Maak het label opnieuw aan.')
                ->danger()
                ->send();

            return null;
        }

        if (! $veloydOrder->label_printed) {
            $veloydOrder->label_printed = true;
            $veloydOrder->save();
        }

        $this->order->refresh();

        $filename = ($veloydOrder->is_return ? 'retour-label-' : 'label-')
            . ($veloydOrder->order->invoice_id ?? $veloydOrder->id)
            . '.pdf';

        return Storage::disk('public')->download($veloydOrder->label_pdf_path, $filename);
    }

    public function render()
    {
        return view('dashed-ecommerce-veloyd::orders.components.show-veloyd-orders');
    }
}
