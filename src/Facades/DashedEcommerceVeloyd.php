<?php

namespace Dashed\DashedEcommerceVeloyd\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Dashed\DashedEcommerceVeloyd\DashedEcommerceVeloyd
 */
class DashedEcommerceVeloyd extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'dashed-ecommerce-veloyd';
    }
}
