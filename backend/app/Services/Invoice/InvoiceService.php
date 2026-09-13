<?php

namespace App\Services\Invoice;

use App\Exceptions\Invoice\InvalidInvoiceTransitionException;
use App\Models\Invoice;
use App\Models\InvoiceStatusHistory;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    public function issue(
        string $invoiceId
    ): Invoice {
        return $this->transition(
            $invoiceId,
            'ISSUED',
            ['DRAFT'],
            null,
            [
                'issued_at' => now(),
            ]
        );
    }

    public function void(
        string $invoiceId,
        string $reason
    ): Invoice {
        return $this->transition(
            $invoiceId,
            'VOID',
            [
                'DRAFT',
                'ISSUED',
            ],
            $reason
        );
    }

    private function transition(
        string $invoiceId,
        string $toState,
        array $allowedFromStates,
        ?string $reason = null,
        array $attributes = []
    ): Invoice {
        return DB::transaction(function () use (
            $invoiceId,
            $toState,
            $allowedFromStates,
            $reason,
            $attributes
        ): Invoice {
            $invoice = $this->baseQuery()
                ->where(
                    'id',
                    $invoiceId
                )
                ->lockForUpdate()
                ->firstOrFail();

            $fromState =
                $invoice->status;

            if (
                ! in_array(
                    $fromState,
                    $allowedFromStates,
                    true
                )
            ) {
                throw new InvalidInvoiceTransitionException(
                    $fromState,
                    $toState
                );
            }

            $invoice->fill(
                array_merge(
                    $attributes,
                    [
                        'status' =>
                            $toState,
                    ]
                )
            );

            $invoice->save();

            InvoiceStatusHistory::query()
                ->create([
                    'id' =>
                        (string) Str::ulid(),

                    'tenant_id' =>
                        $this->tenantContext->tenantId(),

                    'invoice_id' =>
                        $invoice->id,

                    'from_state' =>
                        $fromState,

                    'to_state' =>
                        $toState,

                    'actor_user_id' =>
                        $this->tenantContext->userId(),

                    'reason' =>
                        $reason,

                    'source' =>
                        'USER',

                    'context' => [],

                    'occurred_at' =>
                        now(),
                ]);

            return $this->findOrFail(
                $invoice->id
            );
        });
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
