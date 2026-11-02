<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingZone extends Model
{
    protected $fillable = [
        'name', 'city', 'province', 'postal_codes', 'shipping_fee',
        'free_shipping_threshold', 'estimated_min_days', 'estimated_max_days', 'is_active',
    ];

    protected $casts = [
        'postal_codes' => 'array',
        'shipping_fee' => 'decimal:2',
        'free_shipping_threshold' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
