<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = ['name', 'instructions', 'is_active', 'sort_order'];

    protected $casts = ['is_manual' => 'boolean', 'is_active' => 'boolean'];
}
