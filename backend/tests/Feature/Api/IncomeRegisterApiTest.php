<?php

namespace Tests\Feature\Api;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use App\Models\User;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class IncomeRegisterApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_register_returns_only_incoming_cash_transactions(): void
    {
        $workspace =
            $this->workspace(
                'income-register@example.test',
                'Income Register'
            );

        $account =
            $this->insertAccount(
                $workspace,
                'Kas Utama'
            );

        $paymentLedger =
            $this->insertLedger(
                $workspace,
                $account,
                'IN',
                '125000.00',
                'PAYMENT',
                '2026-09-18 10:00:00',
                'PAY-001',
                'Pembayaran pelanggan'
            );

        $manualLedger =
            $this->insertLedger(
                $workspace,
                $account,
                'IN',
                '50000.00',
                'MANUAL_INCOME',
                '2026-09-17 10:00:00',
                'INC-001',
                'Penjualan barang bekas'
            );

        $this->insertLedger(
            $workspace,
            $account,
            'OUT',
            '25000.00',
            'EXPENSE',
            '2026-09-18 11:00:00',
            'EXP-001',
            'Biaya operasional'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->getJson(
            '/api/v1/finance/income-register'
        )
            ->assertOk()
            ->assertJsonCount(
                2,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $paymentLedger
            )
            ->assertJsonPath(
                'data.0.source.group',
                'CUSTOMER_PAYMENT'
            )
            ->assertJsonPath(
                'data.0.source.type',
                'PAYMENT'
            )
            ->assertJsonPath(
                'data.0.source.automatic',
                true
            )
            ->assertJsonPath(
                'data.0.amount',
                '125000.00'
            )
            ->assertJsonPath(
                'data.0.cash_account.id',
                $account
            )
            ->assertJsonPath(
                'data.1.id',
                $manualLedger
            )
            ->assertJsonPath(
                'data.1.source.group',
                'MANUAL'
            )
            ->assertJsonPath(
                'data.1.source.type',
                'MANUAL_INCOME'
            )
            ->assertJsonPath(
                'data.1.source.automatic',
                false
            );
    }

    public function test_register_filters_group_period_and_search(): void
    {
        $workspace =
            $this->workspace(
                'income-register-filter@example.test',
                'Income Register Filter'
            );

        $account =
            $this->insertAccount(
                $workspace,
                'Bank Operasional'
            );

        $paymentLedger =
            $this->insertLedger(
                $workspace,
                $account,
                'IN',
                '200000.00',
                'PAYMENT',
                '2026-09-10 09:00:00',
                'PAY-SEARCH-001',
                'Pembayaran proyek neon box'
            );

        $this->insertLedger(
            $workspace,
            $account,
            'IN',
            '90000.00',
            'MANUAL_INCOME',
            '2026-09-18 09:00:00',
            'INC-SEARCH-001',
            'Pemasukan lainnya'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->getJson(
            '/api/v1/finance/income-register'
            . '?group=CUSTOMER_PAYMENT'
        )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $paymentLedger
            );

        $this->getJson(
            '/api/v1/finance/income-register'
            . '?from=2026-09-01'
            . '&to=2026-09-15'
        )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $paymentLedger
            );

        $this->getJson(
            '/api/v1/finance/income-register'
            . '?search=neon'
        )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $paymentLedger
            );
    }

    public function test_register_marks_original_receipt_as_reversed(): void
    {
        $workspace =
            $this->workspace(
                'income-register-reversal@example.test',
                'Income Register Reversal'
            );

        $account =
            $this->insertAccount(
                $workspace,
                'Kas Reversal'
            );

        $original =
            $this->insertLedger(
                $workspace,
                $account,
                'IN',
                '150000.00',
                'PAYMENT',
                '2026-09-18 08:00:00',
                'PAY-REV-001',
                'Pembayaran pelanggan'
            );

        $this->insertLedger(
            $workspace,
            $account,
            'OUT',
            '150000.00',
            'PAYMENT_REVERSAL',
            '2026-09-18 09:00:00',
            'PAY-REV-001',
            'Pembalikan pembayaran',
            $original
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->getJson(
            '/api/v1/finance/income-register'
        )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $original
            )
            ->assertJsonPath(
                'data.0.reversed',
                true
            );
    }

    public function test_register_is_business_scoped_within_same_tenant(): void
    {
        $workspace =
            $this->workspace(
                'income-register-business@example.test',
                'Income Register Business'
            );

        $otherBusiness =
            $this->secondBusinessWorkspace(
                $workspace,
                'Income Register Business B'
            );

        $localAccount =
            $this->insertAccount(
                $workspace,
                'Kas Business A'
            );

        $foreignAccount =
            $this->insertAccount(
                $otherBusiness,
                'Kas Business B'
            );

        $localLedger =
            $this->insertLedger(
                $workspace,
                $localAccount,
                'IN',
                '100000.00',
                'MANUAL_INCOME',
                '2026-09-18 10:00:00'
            );

        $this->insertLedger(
            $otherBusiness,
            $foreignAccount,
            'IN',
            '900000.00',
            'MANUAL_INCOME',
            '2026-09-18 11:00:00'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->getJson(
            '/api/v1/finance/income-register'
        )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $localLedger
            )
            ->assertJsonPath(
                'data.0.amount',
                '100000.00'
            );
    }

    public function test_register_validates_filters_and_cash_account_scope(): void
    {
        $workspace =
            $this->workspace(
                'income-register-validation@example.test',
                'Income Register Validation'
            );

        $otherBusiness =
            $this->secondBusinessWorkspace(
                $workspace,
                'Validation Business B'
            );

        $foreignAccount =
            $this->insertAccount(
                $otherBusiness,
                'Bank Business B'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->getJson(
            '/api/v1/finance/income-register'
            . '?group=INVALID'
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );

        $this->getJson(
            '/api/v1/finance/income-register'
            . '?from=2026-09-20'
            . '&to=2026-09-01'
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );

        $this->getJson(
            '/api/v1/finance/income-register'
            . '?cash_account_id='
            . $foreignAccount
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );
    }

    public function test_register_capability_is_enforced(): void
    {
        $workspace =
            $this->workspace(
                'income-register-capability@example.test',
                'Income Register Capability'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->denyCapability(
            $workspace,
            'finance.income.view'
        );

        $this->getJson(
            '/api/v1/finance/income-register'
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );
    }

    private function secondBusinessWorkspace(
        array $workspace,
        string $name
    ): array {
        $businessId =
            (string) Str::ulid();

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
                'Income Register Owner',

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
        string $name
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
                'CASH',

            'bank_name' =>
                null,

            'account_number' =>
                null,

            'account_name' =>
                null,

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
        string $occurredAt,
        ?string $reference = null,
        ?string $description = null,
        ?string $reversalOf = null
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
                $occurredAt,

            'source_type' =>
                $sourceType,

            'source_id' =>
                null,

            'reference' =>
                $reference,

            'description' =>
                $description
                ?? 'Income register test',

            'reversal_of_transaction_id' =>
                $reversalOf,

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
