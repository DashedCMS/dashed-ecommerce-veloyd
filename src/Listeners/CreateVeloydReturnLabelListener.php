<?php

namespace Dashed\DashedEcommerceVeloyd\Listeners;

use Illuminate\Support\Facades\Mail;
use Dashed\DashedCore\Models\Customsetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceVeloyd\Classes\Veloyd;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceVeloyd\Models\VeloydOrder;
use Dashed\DashedEcommerceVeloyd\Mail\ReturnLabelMail;
use Dashed\DashedEcommerceCore\Contracts\ReturnLabelProvider;
use Dashed\DashedEcommerceCore\Events\Orders\OrderReturnApprovedEvent;

/**
 * Genereert en mailt automatisch een Veloyd-retourlabel zodra een
 * OrderReturn is goedgekeurd (handmatig of automatisch).
 *
 * De logica spiegelt de handmatige flow in
 * ShowCreateVeloydReturnLabelOrder: er wordt een nieuwe return-type
 * VeloydOrder aangemaakt met de standaard vervoerder/pakkettype/verzendtype
 * van de regio, Veloyd::createReturnLabelForOrder() gooit bij een fout en
 * geeft bij succes een array met 'filePath' terug, waarna ReturnLabelMail
 * met (Order, filePath, personalNote) wordt verstuurd.
 */
class CreateVeloydReturnLabelListener implements ReturnLabelProvider, ShouldQueue
{
    public function appliesTo(Order $order): bool
    {
        return VeloydOrder::query()->where('order_id', $order->id)->exists();
    }

    public function createAndSendReturnLabel(OrderReturn $orderReturn): bool
    {
        $order = $orderReturn->order;
        if (! $order) {
            return false;
        }

        if (! Veloyd::isConnected($order->site_id)) {
            return false;
        }

        $iso = $order->countryIsoCode;

        // Nieuwe return-type VeloydOrder aanmaken, gelijk aan de handmatige
        // flow. We hergebruiken bewust NIET de bestaande verzend-order, zodat
        // de oorspronkelijke verzending niet wordt overschreven.
        $veloydOrder = $order->veloydOrders()->create([
            'carrier' => Customsetting::get('veloyd_default_carrier_' . $iso, $order->site_id, 'PostNL'),
            'package_type' => Customsetting::get('veloyd_default_package_type_' . $iso, $order->site_id, 1),
            'delivery_type' => Customsetting::get('veloyd_default_delivery_type_' . $iso, $order->site_id, 'Standaard'),
            'is_return' => true,
        ]);

        $result = Veloyd::createReturnLabelForOrder($veloydOrder);

        $filePath = $result['filePath'] ?? null;
        if (! $filePath) {
            return false;
        }

        $orderReturn->return_label_provider = 'veloyd';
        $orderReturn->return_label_path = $filePath;
        $orderReturn->save();

        $email = $orderReturn->email ?: $order->email;
        if ($email) {
            Mail::to($email)->send(new ReturnLabelMail($order, $filePath, null));

            $veloydOrder->is_label_email_sent = true;
            $veloydOrder->save();
        }

        return true;
    }

    public function handle(OrderReturnApprovedEvent $event): void
    {
        try {
            $order = $event->orderReturn->order;
            if (! $order || ! $this->appliesTo($order)) {
                return;
            }
            $this->createAndSendReturnLabel($event->orderReturn);
        } catch (\Throwable $e) {
            report($e);
            $log = new OrderLog();
            $log->order_id = $event->orderReturn->order_id;
            $log->tag = 'order.return-label-failed';
            $log->save();
        }
    }
}
