<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceipt extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'business_id',
        'purchase_order_id',
        'receipt_number',
        'status',
        'warehouse_id',
        'received_at',
        'notes',
        'created_by_user_id',
        'posted_by_user_id',
        'posted_at',
        'reversed_by_user_id',
        'reversed_at',
        'reversal_reason',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'posted_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(
            GoodsReceiptItem::class
        );
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(
            PurchaseOrder::class
        );
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(
            Warehouse::class
        );
    }
}
