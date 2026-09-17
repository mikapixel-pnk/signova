<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'business_id',
        'cash_account_id',
        'amount',
        'currency',
        'incurred_at',
        'category',
        'description',
        'status',
        'created_by_user_id',
        'posted_by_user_id',
        'posted_at',
        'voided_by_user_id',
        'voided_at',
        'void_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'incurred_at' => 'datetime',
        'posted_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(
            CashAccount::class
        );
    }
}
