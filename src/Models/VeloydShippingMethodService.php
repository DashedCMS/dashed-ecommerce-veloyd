<?php

namespace Dashed\DashedEcommerceVeloyd\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class VeloydShippingMethodService extends Model
{
    use LogsActivity;

    protected static $logFillable = true;

    protected $table = 'dashed__veloyd_shipping_method_services';

    protected $fillable = [
        'veloyd_shipping_method_id',
        'name',
        'value',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public function veloydShippingMethod()
    {
        return $this->belongsTo(VeloydShippingMethod::class);
    }

    public function veloydShippingMethodServiceOptions()
    {
        return $this->hasMany(VeloydShippingMethodServiceOption::class);
    }
}
