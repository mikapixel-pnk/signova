<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestItem extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'business_id',
        'purchase_request_id',
        'catalog_item_id',
        'unit_id',
        'item_type',
        'code',
        'name',
        'description',
        'quantity',
        'unit_code',
        'unit_name',
        'unit_symbol',
        'estimated_unit_price',
        'amount',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'estimated_unit_price' => 'decimal:2',
        'amount' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(
            PurchaseRequest::class
        );
    }
}
