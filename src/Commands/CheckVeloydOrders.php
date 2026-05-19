<?php

namespace Dashed\DashedEcommerceVeloyd\Commands;

use Illuminate\Console\Command;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceVeloyd\Classes\Veloyd;

class CheckVeloydOrders extends Command
{
    protected $signature = 'dashed:check-veloyd-orders';

    protected $description = 'Check Veloyd orders and update their status';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        foreach (Order::thisSite()->isPaid()->where('fulfillment_status', '!=', 'handled')->get() as $order) {
            $allVeloydOrdersShipped = false;
            $allVeloydOrdersDelivered = false;

            foreach ($order->veloydOrders()->whereNotNull('shipment_id')->get() as $veloydOrder) {
                $shipment = Veloyd::getShipment($veloydOrder->shipment_id, $order->site_id);
                $statusCode = (int) ($shipment['parcel']['status'] ?? $shipment['status'] ?? 0);

                // Veloyd statussen:
                // 1=registered, 2=confirmed, 3=collected, 4=in transit,
                // 5=manco, 6=available at pickup point, 7=delivered,
                // 8=returned to sender, 9=cancelled, 10=see t&t page.
                if (in_array($statusCode, [7, 8, 9])) {
                    $allVeloydOrdersDelivered = true;
                    $allVeloydOrdersShipped = true;
                } elseif (in_array($statusCode, [3, 4, 5, 6, 10])) {
                    $allVeloydOrdersDelivered = false;
                    $allVeloydOrdersShipped = true;
                }
            }

            if ($allVeloydOrdersDelivered) {
                $order->changeFulfillmentStatus('handled');
            } elseif ($allVeloydOrdersShipped) {
                $order->changeFulfillmentStatus('shipped');
            }
        }
    }
}
