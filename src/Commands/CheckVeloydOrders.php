<?php

namespace Dashed\DashedEcommerceVeloyd\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceVeloyd\Classes\Veloyd;

class CheckVeloydOrders extends Command
{
    protected $signature = 'dashed:check-veloyd-orders';

    protected $description = 'Check Veloyd orders and update their status';

    /** Pause between Veloyd status calls (microseconds) to stay under the rate limit. */
    private const REQUEST_THROTTLE_MICROSECONDS = 250000;

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        Order::thisSite()
            ->isPaid()
            ->where('fulfillment_status', '!=', 'handled')
            ->chunkById(100, function ($orders) {
                foreach ($orders as $order) {
                    try {
                        $this->checkOrder($order);
                    } catch (\Throwable $e) {
                        // One failing order must never abort the whole run.
                        Log::warning("[CheckVeloydOrders] order {$order->id} overgeslagen: {$e->getMessage()}");
                    }
                }
            });
    }

    private function checkOrder(Order $order): void
    {
        $allVeloydOrdersShipped = false;
        $allVeloydOrdersDelivered = false;

        foreach ($order->veloydOrders()->whereNotNull('shipment_id')->get() as $veloydOrder) {
            $shipment = Veloyd::getShipment($veloydOrder->shipment_id, $order->site_id);
            $statusCode = (int) ($shipment['parcel']['status'] ?? $shipment['status'] ?? 0);

            // Throttle so we do not overwhelm the Veloyd API (rate limiting / timeouts).
            usleep(self::REQUEST_THROTTLE_MICROSECONDS);

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
