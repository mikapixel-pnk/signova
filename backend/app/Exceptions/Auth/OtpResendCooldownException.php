<?php

namespace App\Exceptions\Auth;

use RuntimeException;

class OtpResendCooldownException extends RuntimeException
{
    public function __construct(
        private readonly int $retryAfterSeconds
    ) {
        parent::__construct(
            'OTP belum dapat dikirim ulang.'
        );
    }

    public function retryAfterSeconds(): int
    {
        return $this->retryAfterSeconds;
    }
}
