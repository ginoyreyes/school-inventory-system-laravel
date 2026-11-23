<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Item extends Model
{
    protected $fillable = [
        'item_code', 'name', 'category_id', 'description', 'quantity',
        'unit', 'minimum_stock', 'location', 'condition', 'is_active',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'quantity' => 'integer',
        'minimum_stock' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Item $item) {
            if (empty($item->item_code)) {
                $item->item_code = 'TMP-' . uniqid();
            }
        });

        static::created(function (Item $item) {
            if (str_starts_with($item->item_code, 'TMP-')) {
                $item->item_code = 'INV-' . str_pad((string) $item->id, 4, '0', STR_PAD_LEFT);
                $item->saveQuietly();
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function getStatusAttribute(): string
    {
        if ($this->quantity === 0) return 'Out of Stock';
        if ($this->quantity <= $this->minimum_stock) return 'Low Stock';
        return 'In Stock';
    }
}