<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashAccount extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'business_id',
        'name',
        'type',
        'bank_name',
        'account_number',
        'account_name',
        'currency',
        'status',
        'is_default',
        'created_by_user_id',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(
            CashTransaction::class
        );
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(
            Expense::class
        );
    }
}
