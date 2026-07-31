<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingEventDelivery extends Model
{
    protected $fillable = [
        'tracking_integration_id',
        'order_id',
        'event_name',
        'event_id',
        'status',
        'attempts',
        'last_http_status',
        'last_error',
        'sent_at',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'last_http_status' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function integration()
    {
        return $this->belongsTo(TrackingIntegration::class, 'tracking_integration_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
