<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class AuthPasswordResetProof extends Model
{
    use HasUlids;

    protected $table =
        'auth_password_reset_proofs';

    protected $fillable = [
        'user_id',
        'otp_challenge_id',
        'token_hash',
        'expires_at',
        'consumed_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' =>
                'immutable_datetime',

            'consumed_at' =>
                'immutable_datetime',
        ];
    }
}
