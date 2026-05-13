<?php

namespace Dashed\DashedEcommerceVeloyd\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class VeloydShippingMethod extends Model
{
    use LogsActivity;

    protected static $logFillable = true;

    protected $table = 'dashed__veloyd_shipping_methods';

    protected $fillable = [
        'name',
        'value',
        'site_id',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public function veloydShippingMethodServices()
    {
        return $this->hasMany(VeloydShippingMethodService::class);
    }
}
