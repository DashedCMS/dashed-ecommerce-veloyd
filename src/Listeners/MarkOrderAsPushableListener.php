<?php

namespace Dashed\DashedEcommerceVeloyd\Listeners;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Dashed\DashedCore\Models\Customsetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Dashed\DashedEcommerceVeloyd\Classes\Veloyd;
use Dashed\DashedEcommerceCore\Events\Orders\OrderMarkedAsPaidEvent;

class MarkOrderAsPushableListener implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public function __construct()
    {
        //
    }

    public function handle(OrderMarkedAsPaidEvent $event)
    {
        if (Customsetting::get('veloyd_automatically_push_orders', $event->order->site_id)
            && $event->order->street
            && $event->order->order_origin != 'pos'
            && (! $event->order->shippingMethod || $event->order->shippingMethod->sort != 'take_away')) {
            Veloyd::connectOrderWithCarrier($event->order);
        }
    }
}
