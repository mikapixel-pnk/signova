<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class Customer extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'type',
        'code',
        'name',
        'phone',
        'email',
        'tax_id',
        'payment_terms_days',
        'notes',
        'status',
    ];

    protected $casts = [
        'payment_terms_days' => 'integer',
    ];
}
