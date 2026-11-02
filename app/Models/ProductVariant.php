<?php

namespace App\Models;

use App\Enums\Availability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    protected $fillable = ['name', 'sku', 'price', 'sale_price', 'availability', 'track_inventory', 'stock_quantity', 'is_active'];

    protected $casts = [
        'price' => 'decimal:2', 'sale_price' => 'decimal:2',
        'availability' => Availability::class,
        'track_inventory' => 'boolean', 'is_active' => 'boolean',
    ];

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function inventoryMovements(): HasMany { return $this->hasMany(InventoryMovement::class, 'variant_id'); }

    public function getEffectivePriceAttribute(): string
    {
        return Product::effective($this->price, $this->sale_price);
    }
}
