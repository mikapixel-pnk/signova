<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationStatusHistory extends Model
{
    use HasUlids;

    protected $table = 'quotation_status_history';

    protected $fillable = [
        'tenant_id',
        'business_id',
        'quotation_id',
        'from_state',
        'to_state',
        'actor_user_id',
        'reason',
        'source',
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
}
