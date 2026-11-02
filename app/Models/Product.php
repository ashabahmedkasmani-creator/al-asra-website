<?php

namespace App\Models;

use App\Enums\Availability;
use App\Models\Concerns\GeneratesSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use GeneratesSlug, SoftDeletes;

    protected $fillable = [
        'category_id', 'name', 'slug', 'sku', 'short_description', 'description', 'specifications',
        'price', 'sale_price', 'availability', 'track_inventory', 'stock_quantity',
        'is_featured', 'show_on_home',
    ]; // is_published/published_at are set only through publish()/unpublish()

    protected $casts = [
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'specifications' => 'array',
        'availability' => Availability::class,
        'track_inventory' => 'boolean',
        'is_published' => 'boolean',
        'is_featured' => 'boolean',
        'show_on_home' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function images(): HasMany { return $this->hasMany(ProductImage::class)->orderBy('sort_order'); }
    public function variants(): HasMany { return $this->hasMany(ProductVariant::class); }
    public function reviews(): HasMany { return $this->hasMany(Review::class); }
    public function inventoryMovements(): HasMany { return $this->hasMany(InventoryMovement::class); }

    public function scopePublished(Builder $q): Builder { return $q->where('is_published', true); }

    public function getEffectivePriceAttribute(): string
    {
        return self::effective($this->price, $this->sale_price);
    }

    public static function effective(string|float $price, string|float|null $sale): string
    {
        $p = (float) $price;
        return number_format(($sale !== null && (float) $sale < $p) ? (float) $sale : $p, 2, '.', '');
    }

    public function publish(): void { $this->forceFill(['is_published' => true, 'published_at' => $this->published_at ?? now()])->save(); }
    public function unpublish(): void { $this->forceFill(['is_published' => false])->save(); }
}
