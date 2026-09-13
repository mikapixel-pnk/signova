<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentProviderAccount extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'provider',
        'environment',
        'merchant_id',
        'client_key_encrypted',
        'server_key_encrypted',
        'connection_status',
        'last_verified_at',
        'last_success_at',
    ];

    protected $hidden = [
        'client_key_encrypted',
        'server_key_encrypted',
    ];

    protected $casts = [
        'client_key_encrypted' => 'encrypted',
        'server_key_encrypted' => 'encrypted',
        'last_verified_at' => 'datetime',
        'last_success_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(
            Tenant::class
        );
    }
}
