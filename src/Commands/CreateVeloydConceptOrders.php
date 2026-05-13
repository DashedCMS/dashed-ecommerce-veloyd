<?php

namespace Dashed\DashedEcommerceVeloyd\Commands;

use Illuminate\Console\Command;
use Dashed\DashedEcommerceVeloyd\Classes\Veloyd;

class CreateVeloydConceptOrders extends Command
{
    protected $signature = 'dashed:create-veloyd-concept-orders';

    protected $description = 'Create Veloyd concept orders for paid orders without fulfillment status handled';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        Veloyd::createConcepts();
    }
}
