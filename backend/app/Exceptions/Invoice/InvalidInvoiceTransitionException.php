<?php

namespace App\Exceptions\Invoice;

use RuntimeException;

class InvalidInvoiceTransitionException extends RuntimeException
{
    public function __construct(
        public readonly string $fromState,
        public readonly string $toState
    ) {
        parent::__construct(
            "Transisi tagihan dari {$fromState} ke {$toState} tidak diperbolehkan."
        );
    }
}
