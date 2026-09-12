<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'quotation_version_id',
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
        'pricing_method',
        'pricing_config',
        'unit_price',
        'discount_amount',
        'tax_amount',
        'amount',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'pricing_config' => 'array',
        'unit_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'amount' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(
            QuotationVersion::class,
            'quotation_version_id'
        );
    }
}
