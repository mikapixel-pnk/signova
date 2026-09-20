<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPaymentAllocation extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'business_id',
        'supplier_payment_id',
        'supplier_bill_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(
            SupplierPayment::class,
            'supplier_payment_id'
        );
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(
            SupplierBill::class,
            'supplier_bill_id'
        );
    }
}
