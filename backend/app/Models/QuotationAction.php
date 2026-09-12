<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationAction extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'quotation_id',
        'quotation_version_id',
        'public_link_id',
        'action',
        'actor_type',
        'actor_user_id',
        'note',
        'context',
        'occurred_at',
    ];

    protected $casts = [
        'context' => 'array',
        'occurred_at' => 'datetime',
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

    public function publicLink(): BelongsTo
    {
        return $this->belongsTo(
            QuotationPublicLink::class,
            'public_link_id'
        );
    }
}
