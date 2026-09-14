<?php

namespace App\Services\Auth;

use App\Exceptions\Auth\PasswordResetProofException;
use App\Models\AuthOtpChallenge;
use App\Models\AuthPasswordResetProof;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordResetProofService
{
    private const EXPIRY_MINUTES = 10;

    public function issue(
        AuthOtpChallenge $challenge
    ): string {
        if (
            $challenge->purpose
                !== 'PASSWORD_RESET'
            || $challenge->consumed_at === null
        ) {
            throw new PasswordResetProofException(
                'Reset proof tidak valid.'
            );
        }

        return DB::transaction(
            function () use (
                $challenge
            ): string {
                AuthPasswordResetProof::query()
                    ->where(
                        'user_id',
                        $challenge->user_id
                    )
                    ->whereNull(
                        'consumed_at'
                    )
                    ->update([
                        'consumed_at' =>
                            now(),

                        'updated_at' =>
                            now(),
                    ]);

                $secret =
                    Str::random(64);

                $proof =
                    AuthPasswordResetProof::query()
                        ->create([
                            'user_id' =>
                                $challenge
                                    ->user_id,

                            'otp_challenge_id' =>
                                $challenge->id,

                            'token_hash' =>
                                Hash::make(
                                    $secret
                                ),

                            'expires_at' =>
                                now()->addMinutes(
                                    self::EXPIRY_MINUTES
                                ),
                        ]);

                return
                    $proof->id
                    . '.'
                    . $secret;
            }
        );
    }

    public function consume(
        string $token
    ): AuthPasswordResetProof {
        [
            $proofId,
            $secret,
        ] = $this->parseToken(
            $token
        );

        return DB::transaction(
            function () use (
                $proofId,
                $secret
            ): AuthPasswordResetProof {
                $proof =
                    AuthPasswordResetProof::query()
                        ->where(
                            'id',
                            $proofId
                        )
                        ->lockForUpdate()
                        ->first();

                if (
                    ! $proof
                    || $proof->consumed_at
                    || $proof->expires_at
                        ->isPast()
                    || ! Hash::check(
                        $secret,
                        $proof->token_hash
                    )
                ) {
                    throw new PasswordResetProofException(
                        'Reset proof tidak valid.'
                    );
                }

                $proof->consumed_at =
                    now();

                $proof->save();

                return $proof->fresh();
            }
        );
    }

    private function parseToken(
        string $token
    ): array {
        $parts = explode(
            '.',
            $token,
            2
        );

        if (
            count($parts) !== 2
            || trim($parts[0]) === ''
            || trim($parts[1]) === ''
        ) {
            throw new PasswordResetProofException(
                'Reset proof tidak valid.'
            );
        }

        return $parts;
    }
}
