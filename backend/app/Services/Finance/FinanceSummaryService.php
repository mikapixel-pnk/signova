<?php

namespace App\Services\Finance;

use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class FinanceSummaryService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function summary(
        ?string $from = null,
        ?string $to = null
    ): array {
        [$periodFrom, $periodTo] =
            $this->resolvePeriod(
                $from,
                $to
            );

        return [
            'cash_bank' =>
                $this->cashBank(),

            'cashflow' =>
                $this->cashflow(
                    $periodFrom,
                    $periodTo
                ),

            'receivable' =>
                $this->receivable(),

            'period' => [
                'from' =>
                    $periodFrom->toDateString(),

                'to' =>
                    $periodTo->toDateString(),
            ],
        ];
    }

    private function cashBank(): array
    {
        $row =
            DB::table('cash_accounts as ca')
                ->leftJoin(
                    'cash_transactions as ct',
                    function ($join): void {
                        $join
                            ->on(
                                'ct.cash_account_id',
                                '=',
                                'ca.id'
                            )
                            ->on(
                                'ct.tenant_id',
                                '=',
                                'ca.tenant_id'
                            );
                    }
                )
                ->where(
                    'ca.tenant_id',
                    $this->tenantContext
                        ->tenantId()
                )
                ->selectRaw(
                    'COUNT(DISTINCT ca.id) AS account_count'
                )
                ->selectRaw(
                    "
                    COALESCE(
                        SUM(
                            CASE
                                WHEN ct.direction = 'IN'
                                    THEN ct.amount
                                WHEN ct.direction = 'OUT'
                                    THEN -ct.amount
                                ELSE 0
                            END
                        ),
                        0
                    ) AS total_balance
                    "
                )
                ->selectRaw(
                    "
                    COALESCE(
                        SUM(
                            CASE
                                WHEN ca.type = 'CASH'
                                THEN
                                    CASE
                                        WHEN ct.direction = 'IN'
                                            THEN ct.amount
                                        WHEN ct.direction = 'OUT'
                                            THEN -ct.amount
                                        ELSE 0
                                    END
                                ELSE 0
                            END
                        ),
                        0
                    ) AS cash_balance
                    "
                )
                ->selectRaw(
                    "
                    COALESCE(
                        SUM(
                            CASE
                                WHEN ca.type = 'BANK'
                                THEN
                                    CASE
                                        WHEN ct.direction = 'IN'
                                            THEN ct.amount
                                        WHEN ct.direction = 'OUT'
                                            THEN -ct.amount
                                        ELSE 0
                                    END
                                ELSE 0
                            END
                        ),
                        0
                    ) AS bank_balance
                    "
                )
                ->first();

        return [
            'total_balance' =>
                $this->money(
                    $row?->total_balance
                ),

            'cash_balance' =>
                $this->money(
                    $row?->cash_balance
                ),

            'bank_balance' =>
                $this->money(
                    $row?->bank_balance
                ),

            'account_count' =>
                (int) (
                    $row?->account_count
                    ?? 0
                ),
        ];
    }

    private function cashflow(
        CarbonImmutable $from,
        CarbonImmutable $to
    ): array {
        $row =
            DB::table(
                'cash_transactions'
            )
                ->where(
                    'tenant_id',
                    $this->tenantContext
                        ->tenantId()
                )
                ->whereBetween(
                    'occurred_at',
                    [
                        $from,
                        $to,
                    ]
                )
                ->selectRaw(
                    "
                    COALESCE(
                        SUM(
                            CASE
                                WHEN direction = 'IN'
                                    THEN amount
                                ELSE 0
                            END
                        ),
                        0
                    ) AS total_in
                    "
                )
                ->selectRaw(
                    "
                    COALESCE(
                        SUM(
                            CASE
                                WHEN direction = 'OUT'
                                    THEN amount
                                ELSE 0
                            END
                        ),
                        0
                    ) AS total_out
                    "
                )
                ->selectRaw(
                    "
                    COALESCE(
                        SUM(
                            CASE
                                WHEN direction = 'IN'
                                    THEN amount
                                ELSE -amount
                            END
                        ),
                        0
                    ) AS net
                    "
                )
                ->selectRaw(
                    "
                    COALESCE(
                        SUM(
                            CASE
                                WHEN source_type = 'PAYMENT'
                                    AND direction = 'IN'
                                    THEN amount
                                WHEN source_type = 'PAYMENT_REVERSAL'
                                    AND direction = 'OUT'
                                    THEN -amount
                                ELSE 0
                            END
                        ),
                        0
                    ) AS customer_payment_net
                    "
                )
                ->selectRaw(
                    "
                    COALESCE(
                        SUM(
                            CASE
                                WHEN source_type = 'MANUAL_INCOME'
                                    AND direction = 'IN'
                                    THEN amount
                                WHEN source_type = 'MANUAL_INCOME_VOID'
                                    AND direction = 'OUT'
                                    THEN -amount
                                ELSE 0
                            END
                        ),
                        0
                    ) AS manual_income_net
                    "
                )
                ->selectRaw(
                    "
                    COALESCE(
                        SUM(
                            CASE
                                WHEN source_type = 'EXPENSE'
                                    AND direction = 'OUT'
                                    THEN amount
                                WHEN source_type = 'EXPENSE_VOID'
                                    AND direction = 'IN'
                                    THEN -amount
                                ELSE 0
                            END
                        ),
                        0
                    ) AS expense_net
                    "
                )
                ->first();

        return [
            'total_in' =>
                $this->money(
                    $row?->total_in
                ),

            'total_out' =>
                $this->money(
                    $row?->total_out
                ),

            'net' =>
                $this->money(
                    $row?->net
                ),

            'breakdown' => [
                'customer_payment_net' =>
                    $this->money(
                        $row?->customer_payment_net
                    ),

                'manual_income_net' =>
                    $this->money(
                        $row?->manual_income_net
                    ),

                'expense_net' =>
                    $this->money(
                        $row?->expense_net
                    ),
            ],
        ];
    }

    private function receivable(): array
    {
        $base =
            DB::table('invoices')
                ->where(
                    'tenant_id',
                    $this->tenantContext
                        ->tenantId()
                )
                ->whereIn(
                    'status',
                    [
                        'ISSUED',
                        'PARTIALLY_PAID',
                    ]
                )
                ->where(
                    'outstanding_amount',
                    '>',
                    0
                );

        $outstandingTotal =
            (clone $base)
                ->sum(
                    'outstanding_amount'
                );

        $invoiceCount =
            (clone $base)
                ->count();

        $overdue =
            (clone $base)
                ->whereNotNull(
                    'due_at'
                )
                ->where(
                    'due_at',
                    '<',
                    now()
                );

        $overdueTotal =
            (clone $overdue)
                ->sum(
                    'outstanding_amount'
                );

        $overdueCount =
            (clone $overdue)
                ->count();

        return [
            'outstanding_total' =>
                $this->money(
                    $outstandingTotal
                ),

            'overdue_total' =>
                $this->money(
                    $overdueTotal
                ),

            'invoice_count' =>
                $invoiceCount,

            'overdue_count' =>
                $overdueCount,
        ];
    }

    private function resolvePeriod(
        ?string $from,
        ?string $to
    ): array {
        $now =
            CarbonImmutable::now();

        $periodFrom =
            $from !== null
                ? CarbonImmutable::parse(
                    $from
                )->startOfDay()
                : $now->startOfMonth();

        $periodTo =
            $to !== null
                ? CarbonImmutable::parse(
                    $to
                )->endOfDay()
                : $now->endOfMonth();

        return [
            $periodFrom,
            $periodTo,
        ];
    }

    private function money(
        mixed $value
    ): string {
        return bcadd(
            (string) (
                $value
                ?? '0'
            ),
            '0',
            2
        );
    }
}
