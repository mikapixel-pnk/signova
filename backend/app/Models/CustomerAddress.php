<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class CustomerAddress extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'business_id',
        'customer_id',
        'type',
        'label',
        'address_line_1',
        'address_line_2',
        'city',
        'province',
        'postal_code',
        'country_code',
        'is_primary',
        'notes',
        'status',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];
}
