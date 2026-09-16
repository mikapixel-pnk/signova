<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'business_id',
        'code',
        'name',
        'symbol',
        'unit_type',
        'decimal_precision',
        'status',
    ];

    protected $casts = [
        'decimal_precision' => 'integer',
    ];
}
