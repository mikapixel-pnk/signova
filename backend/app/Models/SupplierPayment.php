<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierPayment extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'business_id',
        'payment_number',
        'supplier_id',
        'cash_account_id',
        'status',
        'currency',
        'amount',
        'paid_at',
        'reference',
        'notes',
        'cash_transaction_id',
        'reversal_cash_transaction_id',
        'created_by_user_id',
        'posted_by_user_id',
        'posted_at',
        'reversed_by_user_id',
        'reversed_at',
        'reversal_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'posted_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(
            Supplier::class
        );
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(
            CashAccount::class
        );
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(
            SupplierPaymentAllocation::class
        );
    }

    public function cashTransaction(): BelongsTo
    {
        return $this->belongsTo(
            CashTransaction::class,
            'cash_transaction_id'
        );
    }

    public function reversalCashTransaction(): BelongsTo
    {
        return $this->belongsTo(
            CashTransaction::class,
            'reversal_cash_transaction_id'
        );
    }
}
