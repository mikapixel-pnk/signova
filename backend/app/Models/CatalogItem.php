<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class CatalogItem extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'category_id',
        'unit_id',
        'type',
        'code',
        'name',
        'description',
        'pricing_method',
        'base_price',
        'currency',
        'pricing_config',
        'status',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'pricing_config' => 'array',
    ];
}
