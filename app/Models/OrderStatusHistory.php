<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderStatusHistory extends Model
{
    public $timestamps = false;
    protected $guarded = ['*'];

    protected $casts = ['created_at' => 'datetime'];
}
