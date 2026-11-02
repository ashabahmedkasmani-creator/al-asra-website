<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    protected $fillable = ['order_id', 'courier', 'tracking_number'];

    protected $casts = ['shipped_at' => 'datetime', 'delivered_at' => 'datetime'];
}
