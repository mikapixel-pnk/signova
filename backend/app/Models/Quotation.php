<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'business_id',
        'quotation_number',
        'customer_id',
        'current_version_id',
        'status',
        'valid_until',
        'owner_user_id',
        'source',
        'sent_at',
        'viewed_at',
        'approved_at',
        'rejected_at',
        'cancelled_at',
    ];

    protected $casts = [
        'valid_until' => 'date',
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(
            QuotationVersion::class,
            'current_version_id'
        );
    }

    public function versions(): HasMany
    {
        return $this->hasMany(
            QuotationVersion::class
        );
    }

    public function publicLinks(): HasMany
    {
        return $this->hasMany(
            QuotationPublicLink::class
        );
    }

    public function actions(): HasMany
    {
        return $this->hasMany(
            QuotationAction::class
        );
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(
            QuotationStatusHistory::class
        );
    }
}
