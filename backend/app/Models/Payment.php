<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'amount',
        'currency',
        'paid_at',
        'method',
        'status',
        'reference',
        'evidence_file_id',
        'provider',
        'provider_reference',
        'provider_transaction_id',
        'idempotency_key',
        'created_by_user_id',
        'verified_by_user_id',
        'verified_at',
        'rejected_by_user_id',
        'rejected_at',
        'rejection_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'verified_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(
            Customer::class
        );
    }

    public function evidenceFile(): BelongsTo
    {
        return $this->belongsTo(
            FileAsset::class,
            'evidence_file_id'
        );
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(
            PaymentAllocation::class
        );
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(
            PaymentReversal::class
        );
    }
}
