<?php

namespace App\Exceptions\Quotation;

use RuntimeException;

class InvalidQuotationTransitionException extends RuntimeException
{
    public function __construct(
        public readonly string $fromState,
        public readonly string $toState
    ) {
        parent::__construct(
            "Transisi penawaran dari {$fromState} ke {$toState} tidak diperbolehkan."
        );
    }
}
