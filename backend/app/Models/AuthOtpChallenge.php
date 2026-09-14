<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthOtpChallenge extends Model
{
    use HasUlids;

    protected $table =
        'auth_otp_challenges';

    protected $fillable = [
        'user_id',
        'purpose',
        'channel',
        'code_hash',
        'attempt_count',
        'max_attempts',
        'expires_at',
        'resend_available_at',
        'consumed_at',
        'invalidated_at',
        'delivery_target_hash',
        'requested_ip_hash',
        'user_agent_hash',
    ];

    protected $hidden = [
        'code_hash',
    ];

    protected function casts(): array
    {
        return [
            'attempt_count' =>
                'integer',

            'max_attempts' =>
                'integer',

            'expires_at' =>
                'immutable_datetime',

            'resend_available_at' =>
                'immutable_datetime',

            'consumed_at' =>
                'immutable_datetime',

            'invalidated_at' =>
                'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }
}
