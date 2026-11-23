<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BorrowRecord extends Model
{
    protected $fillable = [
        'reference', 'borrower_name', 'borrower_id_number', 'item_id',
        'quantity', 'date_borrowed', 'expected_return_date',
        'actual_return_date', 'status', 'remarks', 'recorded_by', 'returned_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'date_borrowed' => 'datetime',
        'expected_return_date' => 'datetime',
        'actual_return_date' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (BorrowRecord $borrow) {
            if (empty($borrow->reference)) {
                $borrow->reference = 'TMP-' . uniqid();
            }
        });

        static::created(function (BorrowRecord $borrow) {
            if (str_starts_with($borrow->reference, 'TMP-')) {
                $borrow->reference = 'BRW-' . str_pad((string) $borrow->id, 4, '0', STR_PAD_LEFT);
                $borrow->saveQuietly();
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

    public function returner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function getDisplayStatusAttribute(): string
    {
        if ($this->status === 'Returned') return 'Returned';
        if ($this->expected_return_date && $this->expected_return_date->isPast()) return 'Overdue';
        return 'Borrowed';
    }
}