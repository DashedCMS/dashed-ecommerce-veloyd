<?php

namespace Dashed\DashedEcommerceVeloyd\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Dashed\DashedCore\Models\Customsetting;
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
        $anyShipped = false;
        $allDelivered = true;
        $hasShipments = false;
        $oldestShipmentAt = null;

        foreach ($order->veloydOrders()->whereNotNull('shipment_id')->get() as $veloydOrder) {
            $hasShipments = true;
            $shipment = Veloyd::getShipment($veloydOrder->shipment_id, $order->site_id);
            $statusCode = (int) ($shipment['parcel']['status'] ?? $shipment['status'] ?? 0);

            // Throttle so we do not overwhelm the Veloyd API (rate limiting / timeouts).
            usleep(self::REQUEST_THROTTLE_MICROSECONDS);

            // Veloyd statussen:
            // 1=registered, 2=confirmed, 3=collected, 4=in transit,
            // 5=manco, 6=available at pickup point, 7=delivered,
            // 8=returned to sender, 9=cancelled, 10=see t&t page.
            if (in_array($statusCode, [7, 8, 9], true)) {
                $anyShipped = true;
            } elseif (in_array($statusCode, [3, 4, 5, 6, 10], true)) {
                $anyShipped = true;
                $allDelivered = false;

                // Onthoud het oudste onderweg-zijnde pakket (label-datum ≈
                // verzenddatum) voor de tijd-fallback hieronder.
                $shipmentAt = $veloydOrder->created_at;
                if ($shipmentAt && (! $oldestShipmentAt || $shipmentAt->lt($oldestShipmentAt))) {
                    $oldestShipmentAt = $shipmentAt;
                }
            } else {
                // Nog niet onderweg (registered/confirmed) of onbekend.
                $allDelivered = false;
            }
        }

        if (! $hasShipments || ! $anyShipped) {
            return;
        }

        if ($allDelivered) {
            $order->changeFulfillmentStatus('handled');

            return;
        }

        // Tijd-fallback: Veloyd (of de vervoerder) koppelt lang niet altijd een
        // afgeleverd-status terug, waardoor orders eeuwig op 'shipped' blijven
        // hangen en nooit een opvolg-flow triggeren. Staat een pakket al langer
        // dan de ingestelde drempel onderweg zonder afgeleverd-melding, dan
        // beschouwen we het als afgehandeld. Customsetting = 0 zet dit uit.
        $fallbackDays = (int) Customsetting::get('veloyd_auto_handled_after_shipped_days', $order->site_id, 0);
        if ($fallbackDays > 0 && $oldestShipmentAt && $oldestShipmentAt->lte(now()->subDays($fallbackDays))) {
            $order->changeFulfillmentStatus('handled');

            return;
        }

        $order->changeFulfillmentStatus('shipped');
    }
}
