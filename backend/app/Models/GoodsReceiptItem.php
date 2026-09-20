<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptItem extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'business_id',
        'goods_receipt_id',
        'purchase_order_item_id',
        'material_id',
        'quantity_received',
        'sort_order',
    ];

    protected $casts = [
        'quantity_received' => 'decimal:4',
        'sort_order' => 'integer',
    ];

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(
            PurchaseOrderItem::class
        );
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(
            Material::class
        );
    }
}
