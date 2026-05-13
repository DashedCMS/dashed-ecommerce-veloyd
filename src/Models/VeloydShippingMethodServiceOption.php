<?php

namespace Dashed\DashedEcommerceVeloyd\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class VeloydShippingMethodServiceOption extends Model
{
    use LogsActivity;

    protected static $logFillable = true;

    protected $table = 'dashed__veloyd_shipping_method_service_options';

    protected $fillable = [
        'veloyd_shipping_method_service_id',
        'name',
        'field',
        'type',
        'mandatory',
        'choices',
        'default',
    ];

    protected $casts = [
        'mandatory' => 'boolean',
        'choices' => 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public function veloydShippingMethodService()
    {
        return $this->belongsTo(VeloydShippingMethodService::class);
    }
}
