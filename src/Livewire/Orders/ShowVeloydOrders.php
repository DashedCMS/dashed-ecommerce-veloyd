<?php

namespace Dashed\DashedEcommerceVeloyd\Livewire\Orders;

use Livewire\Component;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use LynX39\LaraPdfMerger\Facades\PdfMerger;
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

    public function downloadLabels(): ?Response
    {
        $labels = $this->downloadableLabels()
            ->filter(fn ($veloydOrder) => Storage::disk('public')->exists($veloydOrder->label_pdf_path));

        if ($labels->isEmpty()) {
            Notification::make()
                ->title('Geen labels om te downloaden')
                ->body('Er staan geen verzendlabels klaar voor deze bestelling.')
                ->danger()
                ->send();

            return null;
        }

        $merger = PdfMerger::init();
        foreach ($labels as $veloydOrder) {
            $merger->addPDF(Storage::disk('public')->path($veloydOrder->label_pdf_path), 'all');
        }
        $merger->merge();

        $outPath = 'dashed/tmp-exports/veloyd-labels-' . $this->order->id . '-' . $this->order->hash . '.pdf';
        Storage::disk('public')->put($outPath, '');
        $merger->save(Storage::disk('public')->path($outPath));

        foreach ($labels as $veloydOrder) {
            if (! $veloydOrder->label_printed) {
                $veloydOrder->label_printed = true;
                $veloydOrder->save();
            }
        }

        $this->order->refresh();

        return Storage::disk('public')->download(
            $outPath,
            'labels-' . ($this->order->invoice_id ?? $this->order->id) . '.pdf'
        );
    }

    public function requeueAll(): void
    {
        $counts = $this->requeueAllLabels();

        if ($counts['concept'] > 0) {
            CreateVeloydConceptOrdersJob::dispatch()->onQueue('ecommerce');
        }

        $this->order->refresh();

        if (($counts['requeued'] + $counts['concept'] + $counts['queued']) === 0) {
            Notification::make()
                ->title('Geen labels om opnieuw in de wachtrij te zetten')
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title('Labels opnieuw in de wachtrij gezet')
            ->body($counts['requeued'] . ' label(s) opnieuw klaargezet voor download'
                . ($counts['concept'] > 0 ? ', ' . $counts['concept'] . ' concept(en) worden aangemaakt' : '')
                . ($counts['queued'] > 0 ? ', ' . $counts['queued'] . ' stond(en) al in de wachtrij' : '')
                . '.')
            ->success()
            ->send();
    }

    public function downloadableLabels(): Collection
    {
        return $this->order->veloydOrders()
            ->where('is_return', false)
            ->whereNotNull('label_pdf_path')
            ->get();
    }

    public function requeueAllLabels(): array
    {
        $requeued = 0;
        $concept = 0;
        $queued = 0;

        foreach ($this->order->veloydOrders()->where('is_return', false)->get() as $veloydOrder) {
            if (! $veloydOrder->shipment_id) {
                $veloydOrder->error = null;
                $veloydOrder->save();
                $concept++;

                continue;
            }

            if ($veloydOrder->label_printed) {
                $veloydOrder->label_printed = false;
                $veloydOrder->save();
                $requeued++;

                continue;
            }

            $queued++;
        }

        return ['requeued' => $requeued, 'concept' => $concept, 'queued' => $queued];
    }

    public function render()
    {
        return view('dashed-ecommerce-veloyd::orders.components.show-veloyd-orders');
    }
}
