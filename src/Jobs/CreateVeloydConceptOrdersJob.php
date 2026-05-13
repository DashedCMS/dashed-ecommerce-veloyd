<?php

namespace Dashed\DashedEcommerceVeloyd\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceVeloyd\Classes\Veloyd;

class CreateVeloydConceptOrdersJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 5;
    public $timeout = 1200;

    public function __construct()
    {
    }

    public function handle(): void
    {
        Veloyd::createConcepts();
    }
}
