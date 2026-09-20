<?php

namespace Tests\Feature\Api;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinanceSummaryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(
            CarbonImmutable::parse(
                '2026-09-14 12:00:00',
                'Asia/Jakarta'
            )
        );

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_summary_returns_cash_bank_cashflow_and_receivable(): void
    {
        $workspace = $this->workspace(
            'finance-summary@example.test',
            'Finance Summary'
        );

        $cashAccount =
            $this->insertAccount(
                $workspace,
                'Kas Utama',
                'CASH'
            );

        $bankAccount =
            $this->insertAccount(
                $workspace,
                'Bank Utama',
                'BANK'
            );

        $this->insertLedger(
            $workspace,
            $cashAccount,
            'IN',
            '500000.00',
            'PAYMENT'
        );

        $this->insertLedger(
            $workspace,
            $cashAccount,
            'OUT',
            '100000.00',
            'PAYMENT_REVERSAL'
        );

        $this->insertLedger(
            $workspace,
            $bankAccount,
            'IN',
            '300000.00',
            'MANUAL_INCOME'
        );

        $this->insertLedger(
            $workspace,
            $bankAccount,
            'OUT',
            '50000.00',
            'MANUAL_INCOME_VOID'
        );

        $this->insertLedger(
            $workspace,
            $cashAccount,
            'OUT',
            '200000.00',
            'EXPENSE'
        );

        $this->insertLedger(
            $workspace,
            $cashAccount,
            'IN',
            '25000.00',
            'EXPENSE_VOID'
        );

        $this->insertLedger(
            $workspace,
            $bankAccount,
            'OUT',
            '120000.00',
            'SUPPLIER_PAYMENT'
        );

        $this->insertLedger(
            $workspace,
            $bankAccount,
            'IN',
            '20000.00',
            'SUPPLIER_PAYMENT_REVERSAL'
        );

        $customerId =
            $this->insertCustomer(
                $workspace
            );

        $this->insertInvoice(
            $workspace,
            $customerId,
            'INV-OVERDUE',
            'ISSUED',
            '1000000.00',
            '700000.00',
            '2026-09-10 12:00:00'
        );

        $this->insertInvoice(
            $workspace,
            $customerId,
            'INV-CURRENT',
            'PARTIALLY_PAID',
            '800000.00',
            '300000.00',
            '2026-09-20 12:00:00'
        );

        $this->insertInvoice(
            $workspace,
            $customerId,
            'INV-PAID',
            'PAID',
            '900000.00',
            '0.00',
            '2026-09-01 12:00:00'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->getJson(
            '/api/v1/finance/summary'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.cash_bank.total_balance',
                '375000.00'
            )
            ->assertJsonPath(
                'data.cash_bank.cash_balance',
                '225000.00'
            )
            ->assertJsonPath(
                'data.cash_bank.bank_balance',
                '150000.00'
            )
            ->assertJsonPath(
                'data.cash_bank.account_count',
                2
            )
            ->assertJsonPath(
                'data.cashflow.total_in',
                '845000.00'
            )
            ->assertJsonPath(
                'data.cashflow.total_out',
                '470000.00'
            )
            ->assertJsonPath(
                'data.cashflow.net',
                '375000.00'
            )
            ->assertJsonPath(
                'data.cashflow.breakdown.customer_payment_net',
                '400000.00'
            )
            ->assertJsonPath(
                'data.cashflow.breakdown.manual_income_net',
                '250000.00'
            )
            ->assertJsonPath(
                'data.cashflow.breakdown.expense_net',
                '175000.00'
            )
            ->assertJsonPath(
                'data.cashflow.breakdown.supplier_payment_net',
                '100000.00'
            )
            ->assertJsonPath(
                'data.receivable.outstanding_total',
                '1000000.00'
            )
            ->assertJsonPath(
                'data.receivable.overdue_total',
                '700000.00'
            )
            ->assertJsonPath(
                'data.receivable.invoice_count',
                2
            )
            ->assertJsonPath(
                'data.receivable.overdue_count',
                1
            )
            ->assertJsonPath(
                'data.period.from',
                '2026-09-01'
            )
            ->assertJsonPath(
                'data.period.to',
                '2026-09-30'
            );
    }

    public function test_cash_bank_balance_is_all_time_but_cashflow_is_period_scoped(): void
    {
        $workspace = $this->workspace(
            'finance-summary-period@example.test',
            'Finance Summary Period'
        );

        $account =
            $this->insertAccount(
                $workspace,
                'Kas Period',
                'CASH'
            );

        $this->insertLedger(
            $workspace,
            $account,
            'IN',
            '100000.00',
            'MANUAL_INCOME',
            '2026-08-31 12:00:00'
        );

        $this->insertLedger(
            $workspace,
            $account,
            'IN',
            '250000.00',
            'MANUAL_INCOME',
            '2026-09-10 12:00:00'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->getJson(
            '/api/v1/finance/summary'
            . '?from=2026-09-01'
            . '&to=2026-09-30'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.cash_bank.total_balance',
                '350000.00'
            )
            ->assertJsonPath(
                'data.cashflow.total_in',
                '250000.00'
            )
            ->assertJsonPath(
                'data.cashflow.net',
                '250000.00'
            );
    }

    public function test_summary_is_tenant_scoped(): void
    {
        $first = $this->workspace(
            'finance-summary-a@example.test',
            'Finance Summary A'
        );

        $second = $this->workspace(
            'finance-summary-b@example.test',
            'Finance Summary B'
        );

        $firstAccount =
            $this->insertAccount(
                $first,
                'Kas A',
                'CASH'
            );

        $secondAccount =
            $this->insertAccount(
                $second,
                'Kas B',
                'CASH'
            );

        $this->insertLedger(
            $first,
            $firstAccount,
            'IN',
            '100000.00',
            'MANUAL_INCOME'
        );

        $this->insertLedger(
            $second,
            $secondAccount,
            'IN',
            '900000.00',
            'MANUAL_INCOME'
        );

        $this->actingAsWorkspace(
            $first
        );

        $this->getJson(
            '/api/v1/finance/summary'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.cash_bank.total_balance',
                '100000.00'
            )
            ->assertJsonPath(
                'data.cash_bank.account_count',
                1
            );
    }

    public function test_summary_validates_period(): void
    {
        $workspace = $this->workspace(
            'finance-summary-validation@example.test',
            'Finance Summary Validation'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->getJson(
            '/api/v1/finance/summary'
            . '?from=2026-09-20'
            . '&to=2026-09-01'
        )
            ->assertStatus(422)
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );
    }

    public function test_summary_capability_is_enforced(): void
    {
        $workspace = $this->workspace(
            'finance-summary-capability@example.test',
            'Finance Summary Capability'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->denyCapability(
            $workspace,
            'finance.summary.view'
        );

        $this->getJson(
            '/api/v1/finance/summary'
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );
    }


    public function test_summary_is_business_scoped_within_same_tenant(): void
    {
        $workspace =
            $this->workspace(
                'summary-business-scope@example.test',
                'Summary Business Scope'
            );

        $otherBusiness =
            $this->secondBusinessWorkspace(
                $workspace,
                'Summary Business B'
            );

        $localAccount =
            $this->insertAccount(
                $workspace,
                'Kas Business A',
                'CASH'
            );

        $foreignAccount =
            $this->insertAccount(
                $otherBusiness,
                'Kas Business B',
                'CASH'
            );

        $this->insertLedger(
            $workspace,
            $localAccount,
            'IN',
            '100000.00',
            'MANUAL_INCOME',
            now()->toDateTimeString()
        );

        $this->insertLedger(
            $otherBusiness,
            $foreignAccount,
            'IN',
            '900000.00',
            'MANUAL_INCOME',
            now()->toDateTimeString()
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->getJson(
            '/api/v1/finance/summary'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.cash_bank.total_balance',
                '100000.00'
            )
            ->assertJsonPath(
                'data.cash_bank.account_count',
                1
            )
            ->assertJsonPath(
                'data.cashflow.total_in',
                '100000.00'
            );
    }


    private function secondBusinessWorkspace(
        array $workspace,
        string $name
    ): array {
        $businessId =
            (string) \Illuminate\Support\Str::ulid();

        DB::table(
            'business_profiles'
        )->insert([
            'id' =>
                $businessId,

            'tenant_id' =>
                $workspace['tenant_id'],

            'name' =>
                $name,

            'is_default' =>
                false,

            'status' =>
                'ACTIVE',

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        return [
            ...$workspace,

            'business_id' =>
                $businessId,
        ];
    }

    private function workspace(
        string $email,
        string $tenantName
    ): array {
        return app(
            CreateTenantWorkspaceAction::class
        )->execute([
            'name' =>
                'Finance Summary Owner',

            'email' =>
                $email,

            'password' =>
                'password',

            'tenant_name' =>
                $tenantName,

            'timezone' =>
                'Asia/Jakarta',
        ]);
    }

    private function actingAsWorkspace(
        array $workspace
    ): void {
        $user =
            User::query()
                ->findOrFail(
                    $workspace['user_id']
                );

        $this->actingAs(
            $user,
            'sanctum'
        );

        $this->withHeader(
            'X-Tenant-ID',
            $workspace['tenant_id']
        );

        $this->withHeader(
            'X-Signova-Business',
            $workspace['business_id']
        );
    }

    private function insertAccount(
        array $workspace,
        string $name,
        string $type
    ): string {
        $id =
            (string) Str::ulid();

        DB::table(
            'cash_accounts'
        )->insert([
            'id' =>
                $id,

            'tenant_id' =>
                $workspace['tenant_id'],

            'business_id' =>
                $workspace['business_id'],

            'name' =>
                $name,

            'type' =>
                $type,

            'bank_name' =>
                $type === 'BANK'
                    ? 'Bank Test'
                    : null,

            'account_number' =>
                null,

            'account_name' =>
                $type === 'BANK'
                    ? 'Finance Summary'
                    : null,

            'currency' =>
                'IDR',

            'status' =>
                'ACTIVE',

            'is_default' =>
                false,

            'created_by_user_id' =>
                $workspace['user_id'],

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        return $id;
    }

    private function insertLedger(
        array $workspace,
        string $cashAccountId,
        string $direction,
        string $amount,
        string $sourceType,
        ?string $occurredAt = null
    ): string {
        $id =
            (string) Str::ulid();

        DB::table(
            'cash_transactions'
        )->insert([
            'id' =>
                $id,

            'tenant_id' =>
                $workspace['tenant_id'],

            'business_id' =>
                $workspace['business_id'],

            'cash_account_id' =>
                $cashAccountId,

            'direction' =>
                $direction,

            'amount' =>
                $amount,

            'currency' =>
                'IDR',

            'occurred_at' =>
                $occurredAt
                    ?? '2026-09-14 12:00:00',

            'source_type' =>
                $sourceType,

            'source_id' =>
                null,

            'reference' =>
                null,

            'description' =>
                'Finance summary test',

            'reversal_of_transaction_id' =>
                null,

            'created_by_user_id' =>
                $workspace['user_id'],

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        return $id;
    }

    private function insertCustomer(
        array $workspace
    ): string {
        $id =
            (string) Str::ulid();

        DB::table(
            'customers'
        )->insert([
            'id' =>
                $id,

            'tenant_id' =>
                $workspace['tenant_id'],

            'business_id' =>
                $workspace['business_id'],

            'name' =>
                'Customer Finance Summary',

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        return $id;
    }

    private function insertInvoice(
        array $workspace,
        string $customerId,
        string $number,
        string $status,
        string $total,
        string $outstanding,
        string $dueAt
    ): string {
        $id =
            (string) Str::ulid();

        DB::table(
            'invoices'
        )->insert([
            'id' =>
                $id,

            'tenant_id' =>
                $workspace['tenant_id'],

            'business_id' =>
                $workspace['business_id'],

            'invoice_number' =>
                $number,

            'customer_id' =>
                $customerId,

            'project_id' =>
                null,

            'source_quotation_id' =>
                null,

            'source_quotation_version_id' =>
                null,

            'status' =>
                $status,

            'issued_at' =>
                '2026-09-01 12:00:00',

            'due_at' =>
                $dueAt,

            'currency' =>
                'IDR',

            'subtotal' =>
                $total,

            'discount_total' =>
                '0.00',

            'tax_total' =>
                '0.00',

            'total' =>
                $total,

            'paid_amount' =>
                bcsub(
                    $total,
                    $outstanding,
                    2
                ),

            'outstanding_amount' =>
                $outstanding,

            'notes' =>
                null,

            'created_by_user_id' =>
                $workspace['user_id'],

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        return $id;
    }

    private function denyCapability(
        array $workspace,
        string $capabilityCode
    ): void {
        $capabilityId =
            DB::table('capabilities')
                ->where(
                    'code',
                    $capabilityCode
                )
                ->value('id');

        $this->assertNotNull(
            $capabilityId
        );

        DB::table(
            'role_capabilities'
        )->updateOrInsert(
            [
                'role_id' =>
                    $workspace[
                        'owner_role_id'
                    ],

                'capability_id' =>
                    $capabilityId,
            ],
            [
                'effect' =>
                    'DENY',

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]
        );
    }
}
