<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Customer extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'business_id',
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

    public function primaryBillingAddress(): HasOne
    {
        return $this->hasOne(
            CustomerAddress::class,
            'customer_id'
        )
            ->where('type', 'BILLING')
            ->where('is_primary', true)
            ->where('status', 'ACTIVE');
    }
}
