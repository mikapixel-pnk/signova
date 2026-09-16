<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuotationPublicLink extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'business_id',
        'quotation_id',
        'quotation_version_id',
        'token_hash',
        'expires_at',
        'revoked_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected $hidden = [
        'token_hash',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(
            Quotation::class
        );
    }

    public function quotationVersion(): BelongsTo
    {
        return $this->belongsTo(
            QuotationVersion::class
        );
    }

    public function actions(): HasMany
    {
        return $this->hasMany(
            QuotationAction::class,
            'public_link_id'
        );
    }
}
