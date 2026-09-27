<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustmentItem extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'business_id',
        'stock_adjustment_id',
        'material_id',
        'quantity_delta',
        'notes',
    ];

    protected $casts = [
        'quantity_delta' => 'decimal:4',
    ];

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(
            StockAdjustment::class,
            'stock_adjustment_id'
        );
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(
            Material::class
        );
    }
}
