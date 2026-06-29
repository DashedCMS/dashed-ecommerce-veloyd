<?php

namespace Dashed\DashedEcommerceVeloyd\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceVeloyd\Classes\Veloyd;
use Dashed\DashedEcommerceVeloyd\Models\VeloydOrder;

/**
 * Koppelt de track & trace van een net-bevestigde Veloyd-zending aan de order
 * zodra de vervoerder 'm toekent. De T&T is bij het printen vaak nog niet
 * beschikbaar (PostNL kent 'm asynchroon toe), dus proberen we het kort daarna
 * een paar keer opnieuw met oplopende vertraging. Lukt het binnen dat venster
 * niet, dan vangt de uurlijkse BackfillVeloydTrackTraces 'm alsnog op.
 */
class SyncVeloydTrackAndTraceJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 3;
    public $timeout = 120;

    /** Vertraging (minuten) per volgende poging; bepaalt ook het aantal pogingen. */
    public const RETRY_DELAYS_MINUTES = [1, 5, 15, 30];

    public function __construct(
        public int $veloydOrderId,
        public int $attempt = 1,
    ) {
    }

    public function handle(): void
    {
        $veloydOrder = VeloydOrder::find($this->veloydOrderId);

        if (! $veloydOrder || ! empty($veloydOrder->track_and_trace)) {
            return;
        }

        if (Veloyd::backfillTrackAndTraceForVeloydOrder($veloydOrder)) {
            return;
        }

        // Nog niet beschikbaar bij de vervoerder: later opnieuw proberen.
        if ($this->attempt < count(self::RETRY_DELAYS_MINUTES)) {
            $delayMinutes = self::RETRY_DELAYS_MINUTES[$this->attempt];

            self::dispatch($this->veloydOrderId, $this->attempt + 1)
                ->delay(now()->addMinutes($delayMinutes));
        }
    }
}
