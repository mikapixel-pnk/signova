<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePublicLink extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'business_id',
        'invoice_id',
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

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(
            Invoice::class
        );
    }
}
