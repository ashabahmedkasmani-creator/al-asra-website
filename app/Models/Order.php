<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    // Orders are only ever created by OrderService; nothing is mass-assignable.
    protected $guarded = ['*'];

    protected $casts = [
        'status' => OrderStatus::class,
        'payment_status' => PaymentStatus::class,
        'subtotal' => 'decimal:2', 'discount_total' => 'decimal:2',
        'shipping_fee' => 'decimal:2', 'grand_total' => 'decimal:2',
        'placed_at' => 'datetime',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function assignedTo(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function shippingZone(): BelongsTo { return $this->belongsTo(ShippingZone::class); }
    public function items(): HasMany { return $this->hasMany(OrderItem::class); }
    public function histories(): HasMany { return $this->hasMany(OrderStatusHistory::class)->orderBy('id'); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
    public function shipment(): HasOne { return $this->hasOne(Shipment::class); }
}
