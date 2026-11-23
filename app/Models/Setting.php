<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'low_stock_threshold', 'borrow_period_days', 'units', 'conditions', 'updated_by',
    ];

    protected $casts = [
        'low_stock_threshold' => 'integer',
        'borrow_period_days' => 'integer',
        'units' => 'array',
        'conditions' => 'array',
    ];
}