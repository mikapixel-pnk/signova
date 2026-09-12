<?php

namespace App\Exceptions\Invoice;

use RuntimeException;

class QuotationToInvoiceConflictException extends RuntimeException
{
    public function __construct(
        private readonly string $errorCode,
        string $message
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
