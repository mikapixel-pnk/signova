<?php

namespace App\Services\Invoice;

use App\Models\Invoice;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class InvoiceService
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {
    }

    public function paginate(
        ?string $search = null,
        ?string $status = null,
        ?string $customerId = null,
        int $perPage = 20
    ): LengthAwarePaginator {
        return $this->baseQuery()
            ->with([
                'customer',
            ])
            ->when(
                $search,
                function (
                    Builder $query,
                    string $search
                ): void {
                    $query->where(
                        function (
                            Builder $query
                        ) use ($search): void {
                            $query
                                ->where(
                                    'invoice_number',
                                    'ILIKE',
                                    '%' . $search . '%'
                                )
                                ->orWhereHas(
                                    'customer',
                                    function (
                                        Builder $query
                                    ) use ($search): void {
                                        $query->where(
                                            'name',
                                            'ILIKE',
                                            '%' . $search . '%'
                                        );
                                    }
                                );
                        }
                    );
                }
            )
            ->when(
                $status,
                fn (
                    Builder $query,
                    string $status
                ) => $query->where(
                    'status',
                    $status
                )
            )
            ->when(
                $customerId,
                fn (
                    Builder $query,
                    string $customerId
                ) => $query->where(
                    'customer_id',
                    $customerId
                )
            )
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function findOrFail(
        string $invoiceId
    ): Invoice {
        return $this->baseQuery()
            ->with([
                'customer',
                'items',
                'statusHistory',
            ])
            ->where(
                'id',
                $invoiceId
            )
            ->firstOrFail();
    }

    private function baseQuery(): Builder
    {
        return Invoice::query()
            ->where(
                'tenant_id',
                $this->tenantContext->tenantId()
            );
    }
}
