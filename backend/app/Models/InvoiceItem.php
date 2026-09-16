<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $table = 'invoice_items';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'business_id',
        'invoice_id',
        'source_quotation_item_id',
        'catalog_item_id',
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
}
