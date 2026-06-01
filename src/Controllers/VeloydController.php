<?php

namespace Dashed\DashedEcommerceVeloyd\Controllers;

use App\Http\Controllers\Controller;
use Filament\Notifications\Notification;
use Dashed\DashedEcommerceVeloyd\Jobs\CreateShippingLabelsJob;

class VeloydController extends Controller
{
    public function downloadLabels()
    {
        CreateShippingLabelsJob::dispatch(auth()->user())->onQueue('ecommerce');

        Notification::make()
            ->body('Labels worden aangemaakt, ze staan over een paar minuten klaar om te downloaden')
            ->success()
            ->send();

        return redirect()->back();
    }
}
