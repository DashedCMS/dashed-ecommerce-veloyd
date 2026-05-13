<?php

namespace Dashed\DashedEcommerceVeloyd\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedEcommerceVeloyd\Classes\Veloyd;
use Dashed\DashedEcommerceVeloyd\Models\VeloydOrder;

class VeloydController extends Controller
{
    public function downloadLabels()
    {
        $veloydOrders = VeloydOrder::where('label_printed', 0)->get();

        $response = Veloyd::getLabelsFromShipments($veloydOrders->pluck('shipment_id')->toArray());

        if (! empty($response['labels'])) {
            $fileName = '/dashed/veloyd/labels/labels-' . time() . '.pdf';

            // Veloyd levert per shipment een eigen base64 PDF; we slaan ze los
            // op en geven het eerste terug. Bulk-download met merge gebeurt
            // via createShipments() / CreateShippingLabelsJob.
            $firstLabel = array_values($response['labels'])[0] ?? null;
            if ($firstLabel) {
                Storage::disk('dashed')->put($fileName, base64_decode($firstLabel));
                foreach ($veloydOrders as $veloydOrder) {
                    $veloydOrder->label_printed = 1;
                    $veloydOrder->save();
                }

                return Storage::disk('dashed')->download($fileName);
            }
        }

        echo "<script>window.close();</script>";
    }
}
