<?php

namespace Dashed\DashedEcommerceVeloyd\Listeners;

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceVeloyd\Classes\Veloyd;
use Dashed\DashedEcommerceCore\Events\Orders\OrderMarkedAsPaidEvent;

class MarkOrderAsPushableListener
{
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
