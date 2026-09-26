<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Material extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'business_id',
        'code',
        'name',
        'unit_id',

        /*
         * Legacy compatibility field.
         * Source of truth kategori baru adalah category_id.
         */
        'category',

        'category_id',
        'inventory_type',
        'stock_tracking',
        'minimum_stock',
        'reorder_point',
        'maximum_stock',
        'description',
        'status',
    ];

    protected $casts = [
        'minimum_stock' =>
            'decimal:4',

        'reorder_point' =>
            'decimal:4',

        'maximum_stock' =>
            'decimal:4',
    ];

    public function inventoryCategory(): BelongsTo
    {
        return $this->belongsTo(
            InventoryCategory::class,
            'category_id'
        );
    }
}
