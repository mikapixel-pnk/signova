<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashTransaction extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'cash_account_id',
        'direction',
        'amount',
        'currency',
        'occurred_at',
        'source_type',
        'source_id',
        'reference',
        'description',
        'reversal_of_transaction_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'occurred_at' => 'datetime',
    ];

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(
            CashAccount::class
        );
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'reversal_of_transaction_id'
        );
    }
}
