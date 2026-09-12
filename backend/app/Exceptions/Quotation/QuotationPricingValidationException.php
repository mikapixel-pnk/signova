<?php

namespace App\Exceptions\Quotation;

use InvalidArgumentException;

class QuotationPricingValidationException extends InvalidArgumentException
{
    public function __construct(
        string $message,
        private readonly string $field
    ) {
        parent::__construct($message);
    }

    public function field(): string
    {
        return $this->field;
    }

    public function withPrefix(string $prefix): self
    {
        return new self(
            $this->getMessage(),
            $prefix . $this->field
        );
    }
}
