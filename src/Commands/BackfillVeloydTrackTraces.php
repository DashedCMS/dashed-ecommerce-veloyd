<?php

namespace Dashed\DashedEcommerceVeloyd\Commands;

use Illuminate\Console\Command;
use Dashed\DashedEcommerceVeloyd\Classes\Veloyd;

class BackfillVeloydTrackTraces extends Command
{
    protected $signature = 'dashed:backfill-veloyd-track-traces';

    protected $description = 'Backfill ontbrekende track & traces voor bestaande Veloyd-zendingen (ongeacht status), zodat downstream syncs zoals Bol.com ze alsnog oppakken.';

    public function handle(): int
    {
        $this->info('Veloyd track & trace backfill gestart...');

        $result = Veloyd::backfillMissingTrackAndTraces(function ($vo, $gotTrackTrace): void {
            $this->line(($gotTrackTrace ? '  [OK]   ' : '  [leeg] ') . 'order #' . ($vo->order_id ?? '?') . ' / shipment ' . $vo->shipment_id);
        });

        $this->info("Klaar. Zendingen zonder T&T: {$result['total']} | gekoppeld: {$result['fixed']} | nog leeg (API heeft nog geen T&T): {$result['stillEmpty']}.");

        return self::SUCCESS;
    }
}
