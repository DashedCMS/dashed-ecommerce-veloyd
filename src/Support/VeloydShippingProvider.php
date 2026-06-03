<?php

namespace Dashed\DashedEcommerceVeloyd\Support;

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceVeloyd\Models\VeloydOrder;
use Dashed\DashedEcommerceCore\Contracts\ShippingLabelProvider;

class VeloydShippingProvider implements ShippingLabelProvider
{
    public function key(): string
    {
        return 'veloyd';
    }

    public function label(): string
    {
        return 'Veloyd';
    }

    public function failedOrders(): array
    {
        $siteId = Sites::getActive();

        return VeloydOrder::whereNotNull('error')
            ->where('label_printed', 0)
            ->whereHas('order', fn ($q) => $q->where('site_id', $siteId))
            ->with('order')
            ->get()
            ->map(fn (VeloydOrder $o) => [
                'id' => $o->id,
                'order_id' => $o->order_id,
                'invoice_id' => $o->order?->invoice_id,
                'error' => (string) $o->error,
            ])
            ->all();
    }

    public function retry(int $id): void
    {
        $order = VeloydOrder::find($id);
        if ($order) {
            $order->error = null;
            $order->save();
        }
    }
}
