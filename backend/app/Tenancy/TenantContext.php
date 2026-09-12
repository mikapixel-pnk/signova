<?php

namespace App\Tenancy;

use RuntimeException;

class TenantContext
{
    private ?string $tenantId = null;

    private ?string $userId = null;

    public function set(string $tenantId, string $userId): void
    {
        $this->tenantId = $tenantId;
        $this->userId = $userId;
    }

    public function clear(): void
    {
        $this->tenantId = null;
        $this->userId = null;
    }

    public function tenantId(): string
    {
        if ($this->tenantId === null) {
            throw new RuntimeException(
                'Tenant context has not been resolved.'
            );
        }

        return $this->tenantId;
    }

    public function userId(): string
    {
        if ($this->userId === null) {
            throw new RuntimeException(
                'Tenant context has not been resolved.'
            );
        }

        return $this->userId;
    }

    public function hasTenant(): bool
    {
        return $this->tenantId !== null;
    }
}
