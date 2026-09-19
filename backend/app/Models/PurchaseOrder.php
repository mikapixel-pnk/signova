<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'business_id',
        'order_number',
        'supplier_id',
        'source_purchase_request_id',
        'status',
        'currency',
        'expected_at',
        'subtotal',
        'discount_total',
        'tax_total',
        'total',
        'notes',
        'created_by_user_id',
        'issued_by_user_id',
        'issued_at',
        'cancelled_by_user_id',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'expected_at' => 'date',
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total' => 'decimal:2',
        'issued_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(
            PurchaseOrderItem::class
        );
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(
            PurchaseOrderStatusHistory::class
        );
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(
            Supplier::class
        );
    }

    public function sourcePurchaseRequest(): BelongsTo
    {
        return $this->belongsTo(
            PurchaseRequest::class,
            'source_purchase_request_id'
        );
    }
}
