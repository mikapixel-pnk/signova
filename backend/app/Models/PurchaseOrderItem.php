<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'business_id',
        'purchase_order_id',
        'source_purchase_request_item_id',
        'catalog_item_id',
        'material_id',
        'unit_id',
        'procurement_type',
        'item_type',
        'code',
        'name',
        'description',
        'quantity',
        'unit_code',
        'unit_name',
        'unit_symbol',
        'unit_price',
        'discount_amount',
        'tax_amount',
        'amount',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'amount' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(
            PurchaseOrder::class
        );
    }

    public function sourcePurchaseRequestItem(): BelongsTo
    {
        return $this->belongsTo(
            PurchaseRequestItem::class,
            'source_purchase_request_item_id'
        );
    }
}
