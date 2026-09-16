<?php

namespace App\Tenancy;

use RuntimeException;

class BusinessContext
{
    private ?string $tenantId = null;

    private ?string $businessId = null;

    private ?string $userId = null;

    public function set(
        string $tenantId,
        string $businessId,
        string $userId
    ): void {
        $this->tenantId = $tenantId;
        $this->businessId = $businessId;
        $this->userId = $userId;
    }

    public function clear(): void
    {
        $this->tenantId = null;
        $this->businessId = null;
        $this->userId = null;
    }

    public function tenantId(): string
    {
        if ($this->tenantId === null) {
            throw new RuntimeException(
                'Business context has not been resolved.'
            );
        }

        return $this->tenantId;
    }

    public function businessId(): string
    {
        if ($this->businessId === null) {
            throw new RuntimeException(
                'Business context has not been resolved.'
            );
        }

        return $this->businessId;
    }

    public function userId(): string
    {
        if ($this->userId === null) {
            throw new RuntimeException(
                'Business context has not been resolved.'
            );
        }

        return $this->userId;
    }

    public function hasBusiness(): bool
    {
        return $this->businessId !== null;
    }
}
