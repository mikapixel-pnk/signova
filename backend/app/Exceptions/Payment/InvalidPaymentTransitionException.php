<?php

namespace App\Exceptions\Payment;

use RuntimeException;

class InvalidPaymentTransitionException extends RuntimeException
{
    public function __construct(
        public readonly string $fromState,
        public readonly string $toState
    ) {
        parent::__construct(
            "Transisi pembayaran dari {$fromState} ke {$toState} tidak diperbolehkan."
        );
    }
}
