<?php

namespace App\Services\Auth\Otp;

use Carbon\CarbonImmutable;

final readonly class IssuedOtpChallenge
{
    public function __construct(
        public string $challengeId,
        public string $code,
        public CarbonImmutable $expiresAt,
        public CarbonImmutable $resendAvailableAt,
    ) {
    }
}
