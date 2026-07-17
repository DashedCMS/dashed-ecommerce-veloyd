<?php

namespace Dashed\DashedEcommerceVeloyd\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Dashed\DashedEcommerceCore\Models\Order;

class VeloydOrder extends Model
{
    use LogsActivity;

    protected static $logFillable = true;

    protected $table = 'dashed__order_veloyd';

    protected $fillable = [
        'order_id',
        'shipment_id',
        'label',
        'label_url',
        'track_and_trace',
        'label_printed',
        'carrier',
        'package_type',
        'delivery_type',
        'options',
        'error',
        'is_return',
        'is_label_email_sent',
        'personal_note',
        'label_pdf_path',
    ];

    protected $casts = [
        'track_and_trace' => 'array',
        'options' => 'array',
        'label_printed' => 'boolean',
        'is_return' => 'boolean',
        'is_label_email_sent' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
