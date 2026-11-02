<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'description', 'type', 'value', 'min_subtotal', 'max_discount',
        'usage_limit', 'per_customer_limit', 'starts_at', 'expires_at', 'is_active',
    ]; // used_count is system-managed

    protected $casts = [
        'value' => 'decimal:2', 'min_subtotal' => 'decimal:2', 'max_discount' => 'decimal:2',
        'starts_at' => 'datetime', 'expires_at' => 'datetime', 'is_active' => 'boolean',
    ];
}
