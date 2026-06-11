<?php

namespace Dashed\DashedEcommerceVeloyd\Commands;

use Illuminate\Console\Command;
use Dashed\DashedEcommerceVeloyd\Classes\Veloyd;

class SyncVeloydStatuses extends Command
{
    protected $signature = 'dashed:sync-veloyd-statuses';

    protected $description = 'Synct de bezorgstatus (onderweg/geleverd) van Veloyd-zendingen naar de labelstatus.';

    public function handle(): int
    {
        $count = Veloyd::syncShipmentStatuses();
        $this->info("Veloyd-labelstatussen bijgewerkt: {$count}.");

        return self::SUCCESS;
    }
}
