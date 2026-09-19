<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'business_id',
        'code',
        'name',
        'contact_name',
        'phone',
        'email',
        'tax_id',
        'address',
        'city',
        'province',
        'payment_terms_days',
        'notes',
        'status',
    ];

    protected $casts = [
        'payment_terms_days' => 'integer',
    ];
}
