<?php

namespace App\Exceptions\Payment;

use RuntimeException;

class PaymentEvidenceLockedException extends RuntimeException
{
    public function __construct(
        string $status
    ) {
        parent::__construct(
            "Bukti pembayaran tidak dapat diubah pada status {$status}."
        );
    }
}
