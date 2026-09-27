<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockAdjustment extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'business_id',
        'adjustment_number',
        'warehouse_id',
        'status',
        'reason',
        'notes',
        'adjusted_at',
        'created_by_user_id',
        'posted_by_user_id',
        'posted_at',
        'reversed_by_user_id',
        'reversed_at',
        'reversal_reason',
    ];

    protected $casts = [
        'adjusted_at' => 'datetime',
        'posted_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(
            StockAdjustmentItem::class
        );
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(
            Warehouse::class
        );
    }
}
