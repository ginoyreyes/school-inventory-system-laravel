<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransaction extends Model
{
    protected $fillable = [
        'reference', 'type', 'item_id', 'quantity', 'balance_after',
        'date', 'supplier', 'reason', 'remarks', 'recorded_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'balance_after' => 'integer',
        'date' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (StockTransaction $txn) {
            if (empty($txn->reference)) {
                $txn->reference = 'TMP-' . uniqid();
            }
        });

        static::created(function (StockTransaction $txn) {
            if (str_starts_with($txn->reference, 'TMP-')) {
                $txn->reference = 'TXN-' . str_pad((string) $txn->id, 5, '0', STR_PAD_LEFT);
                $txn->saveQuietly();
            }
        });
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}