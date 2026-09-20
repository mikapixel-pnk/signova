<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRequest extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'business_id',
        'request_number',
        'status',
        'needed_at',
        'currency',
        'estimated_total',
        'notes',
        'requested_by_user_id',
        'submitted_by_user_id',
        'submitted_at',
        'approved_by_user_id',
        'approved_at',
        'rejected_by_user_id',
        'rejected_at',
        'rejection_reason',
        'cancelled_by_user_id',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'needed_at' => 'date',
        'estimated_total' => 'decimal:2',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(
            PurchaseRequestItem::class
        );
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(
            PurchaseRequestStatusHistory::class
        );
    }
}
