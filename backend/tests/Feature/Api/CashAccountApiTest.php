<?php

namespace Tests\Feature\Api;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use App\Models\User;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CashAccountApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_first_cash_account_is_active_and_default(): void
    {
        $workspace = $this->workspace(
            'cash-first@example.test',
            'Cash First'
        );

        $this->actingAsWorkspace($workspace);

        $response = $this->postJson(
            '/api/v1/finance/cash-accounts',
            [
                'name' => 'Kas Utama',
                'type' => 'cash',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.name',
                'Kas Utama'
            )
            ->assertJsonPath(
                'data.type',
                'CASH'
            )
            ->assertJsonPath(
                'data.status',
                'ACTIVE'
            )
            ->assertJsonPath(
                'data.is_default',
                true
            )
            ->assertJsonPath(
                'data.currency',
                'IDR'
            )
            ->assertJsonPath(
                'data.balance',
                '0.00'
            );

        $this->assertDatabaseHas(
            'cash_accounts',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],
                'name' =>
                    'Kas Utama',
                'type' =>
                    'CASH',
                'status' =>
                    'ACTIVE',
                'is_default' =>
                    true,
            ]
        );
    }

    public function test_second_account_is_not_default(): void
    {
        $workspace = $this->workspace(
            'cash-second@example.test',
            'Cash Second'
        );

        $this->actingAsWorkspace($workspace);

        $this->createAccount(
            'Kas Utama',
            'CASH'
        );

        $response =
            $this->createAccount(
                'Bank Operasional',
                'BANK',
                [
                    'bank_name' =>
                        'Bank Test',
                    'account_name' =>
                        'PT Test',
                ]
            );

        $response
            ->assertJsonPath(
                'data.is_default',
                false
            );
    }

    public function test_cash_account_clears_bank_metadata(): void
    {
        $workspace = $this->workspace(
            'cash-metadata@example.test',
            'Cash Metadata'
        );

        $this->actingAsWorkspace($workspace);

        $response = $this->postJson(
            '/api/v1/finance/cash-accounts',
            [
                'name' =>
                    'Kas Toko',
                'type' =>
                    'CASH',
                'bank_name' =>
                    'Harus Dibuang',
                'account_number' =>
                    '999',
                'account_name' =>
                    'Harus Dibuang',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.bank_name',
                null
            )
            ->assertJsonPath(
                'data.account_number',
                null
            )
            ->assertJsonPath(
                'data.account_name',
                null
            );
    }

    public function test_bank_requires_bank_name_and_account_name(): void
    {
        $workspace = $this->workspace(
            'cash-bank-validation@example.test',
            'Cash Bank Validation'
        );

        $this->actingAsWorkspace($workspace);

        $this->postJson(
            '/api/v1/finance/cash-accounts',
            [
                'name' =>
                    'Bank Invalid',
                'type' =>
                    'BANK',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            )
            ->assertJsonStructure([
                'error' => [
                    'details' => [
                        'fields' => [
                            'bank_name',
                            'account_name',
                        ],
                    ],
                ],
            ]);
    }

    public function test_list_is_tenant_scoped_filterable_and_has_computed_balance(): void
    {
        $first = $this->workspace(
            'cash-list-a@example.test',
            'Cash List A'
        );

        $second = $this->workspace(
            'cash-list-b@example.test',
            'Cash List B'
        );

        $firstAccount =
            $this->insertAccount(
                $first,
                'Bank Alpha',
                'BANK',
                false
            );

        $this->insertAccount(
            $first,
            'Kas Beta',
            'CASH',
            false
        );

        $this->insertAccount(
            $second,
            'Bank Alpha Tenant B',
            'BANK',
            false
        );

        $this->cashTransaction(
            $first,
            $firstAccount,
            'IN',
            '250000.00'
        );

        $this->cashTransaction(
            $first,
            $firstAccount,
            'OUT',
            '50000.00'
        );

        $this->actingAsWorkspace($first);

        $response = $this->getJson(
            '/api/v1/finance/cash-accounts'
            . '?search=Alpha&type=BANK&status=ACTIVE'
        );

        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $firstAccount
            )
            ->assertJsonPath(
                'data.0.balance',
                '200000.00'
            )
            ->assertJsonPath(
                'meta.total',
                1
            );
    }

    public function test_foreign_account_detail_returns_not_found(): void
    {
        $first = $this->workspace(
            'cash-detail-a@example.test',
            'Cash Detail A'
        );

        $second = $this->workspace(
            'cash-detail-b@example.test',
            'Cash Detail B'
        );

        $foreignId =
            $this->insertAccount(
                $second,
                'Kas Tenant B',
                'CASH',
                false
            );

        $this->actingAsWorkspace($first);

        $this->getJson(
            '/api/v1/finance/cash-accounts/'
            . $foreignId
        )
            ->assertNotFound()
            ->assertJsonPath(
                'error.code',
                'RESOURCE_NOT_FOUND'
            );
    }

    public function test_bank_can_be_changed_to_cash_and_bank_fields_are_cleared(): void
    {
        $workspace = $this->workspace(
            'cash-update@example.test',
            'Cash Update'
        );

        $accountId =
            $this->insertAccount(
                $workspace,
                'Bank Lama',
                'BANK',
                false,
                'Bank Test',
                '12345',
                'PT Test'
            );

        $this->actingAsWorkspace($workspace);

        $this->patchJson(
            '/api/v1/finance/cash-accounts/'
            . $accountId,
            [
                'name' =>
                    'Kas Baru',
                'type' =>
                    'CASH',
                'status' =>
                    'INACTIVE',
                'is_default' =>
                    true,
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.type',
                'CASH'
            )
            ->assertJsonPath(
                'data.bank_name',
                null
            )
            ->assertJsonPath(
                'data.account_number',
                null
            )
            ->assertJsonPath(
                'data.account_name',
                null
            )
            ->assertJsonPath(
                'data.status',
                'ACTIVE'
            )
            ->assertJsonPath(
                'data.is_default',
                false
            );
    }

    public function test_non_default_account_can_be_deactivated_and_activated(): void
    {
        $workspace = $this->workspace(
            'cash-state@example.test',
            'Cash State'
        );

        $default =
            $this->insertAccount(
                $workspace,
                'Kas Default',
                'CASH',
                true
            );

        $account =
            $this->insertAccount(
                $workspace,
                'Kas Cabang',
                'CASH',
                false
            );

        $this->actingAsWorkspace($workspace);

        $this->postJson(
            '/api/v1/finance/cash-accounts/'
            . $account
            . '/actions/deactivate'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'INACTIVE'
            );

        $this->postJson(
            '/api/v1/finance/cash-accounts/'
            . $account
            . '/actions/activate'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'ACTIVE'
            );

        $this->assertDatabaseHas(
            'cash_accounts',
            [
                'id' => $default,
                'is_default' => true,
            ]
        );
    }

    public function test_default_account_cannot_be_deactivated(): void
    {
        $workspace = $this->workspace(
            'cash-default-deactivate@example.test',
            'Cash Default Deactivate'
        );

        $account =
            $this->insertAccount(
                $workspace,
                'Kas Default',
                'CASH',
                true
            );

        $this->actingAsWorkspace($workspace);

        $this->postJson(
            '/api/v1/finance/cash-accounts/'
            . $account
            . '/actions/deactivate'
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'ENTITY_STATE_CONFLICT'
            );

        $this->assertDatabaseHas(
            'cash_accounts',
            [
                'id' => $account,
                'status' => 'ACTIVE',
                'is_default' => true,
            ]
        );
    }

    public function test_set_default_moves_default_atomically(): void
    {
        $workspace = $this->workspace(
            'cash-set-default@example.test',
            'Cash Set Default'
        );

        $old =
            $this->insertAccount(
                $workspace,
                'Kas Lama',
                'CASH',
                true
            );

        $new =
            $this->insertAccount(
                $workspace,
                'Bank Baru',
                'BANK',
                false
            );

        $this->actingAsWorkspace($workspace);

        $this->postJson(
            '/api/v1/finance/cash-accounts/'
            . $new
            . '/actions/set-default'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $new
            )
            ->assertJsonPath(
                'data.is_default',
                true
            );

        $this->assertDatabaseHas(
            'cash_accounts',
            [
                'id' => $old,
                'is_default' => false,
            ]
        );

        $this->assertDatabaseHas(
            'cash_accounts',
            [
                'id' => $new,
                'is_default' => true,
            ]
        );
    }

    public function test_inactive_account_cannot_be_set_default(): void
    {
        $workspace = $this->workspace(
            'cash-inactive-default@example.test',
            'Cash Inactive Default'
        );

        $this->insertAccount(
            $workspace,
            'Kas Default',
            'CASH',
            true
        );

        $inactive =
            $this->insertAccount(
                $workspace,
                'Kas Nonaktif',
                'CASH',
                false,
                null,
                null,
                null,
                'INACTIVE'
            );

        $this->actingAsWorkspace($workspace);

        $this->postJson(
            '/api/v1/finance/cash-accounts/'
            . $inactive
            . '/actions/set-default'
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'ENTITY_STATE_CONFLICT'
            );
    }

    public function test_unused_non_default_account_can_be_deleted(): void
    {
        $workspace = $this->workspace(
            'cash-delete-unused@example.test',
            'Cash Delete Unused'
        );

        $this->insertAccount(
            $workspace,
            'Kas Default',
            'CASH',
            true
        );

        $unused =
            $this->insertAccount(
                $workspace,
                'Kas Salah Input',
                'CASH',
                false
            );

        $this->actingAsWorkspace($workspace);

        $this->deleteJson(
            '/api/v1/finance/cash-accounts/'
            . $unused
        )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $unused
            );

        $this->assertDatabaseMissing(
            'cash_accounts',
            [
                'id' => $unused,
            ]
        );
    }

    public function test_default_account_cannot_be_deleted(): void
    {
        $workspace = $this->workspace(
            'cash-delete-default@example.test',
            'Cash Delete Default'
        );

        $account =
            $this->insertAccount(
                $workspace,
                'Kas Default',
                'CASH',
                true
            );

        $this->actingAsWorkspace($workspace);

        $this->deleteJson(
            '/api/v1/finance/cash-accounts/'
            . $account
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'ENTITY_STATE_CONFLICT'
            );
    }

    public function test_account_with_cash_transaction_cannot_be_deleted_even_when_balance_is_zero(): void
    {
        $workspace = $this->workspace(
            'cash-delete-ledger@example.test',
            'Cash Delete Ledger'
        );

        $this->insertAccount(
            $workspace,
            'Kas Default',
            'CASH',
            true
        );

        $account =
            $this->insertAccount(
                $workspace,
                'Kas History',
                'CASH',
                false
            );

        $this->cashTransaction(
            $workspace,
            $account,
            'IN',
            '100000.00'
        );

        $this->cashTransaction(
            $workspace,
            $account,
            'OUT',
            '100000.00'
        );

        $this->actingAsWorkspace($workspace);

        $this->deleteJson(
            '/api/v1/finance/cash-accounts/'
            . $account
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'ENTITY_HAS_ACTIVITY'
            );

        $this->assertDatabaseHas(
            'cash_accounts',
            [
                'id' => $account,
            ]
        );
    }

    public function test_account_referenced_by_payment_cannot_be_deleted(): void
    {
        $workspace = $this->workspace(
            'cash-delete-payment@example.test',
            'Cash Delete Payment'
        );

        $this->insertAccount(
            $workspace,
            'Kas Default',
            'CASH',
            true
        );

        $account =
            $this->insertAccount(
                $workspace,
                'Bank Payment',
                'BANK',
                false
            );

        $customerId =
            $this->insertCustomer(
                $workspace
            );

        DB::table('payments')->insert([
            'id' => (string) Str::ulid(),
            'tenant_id' =>
                $workspace['tenant_id'],
            'customer_id' =>
                $customerId,
            'cash_account_id' =>
                $account,
            'amount' =>
                '100000.00',
            'currency' =>
                'IDR',
            'paid_at' =>
                now(),
            'method' =>
                'BANK_TRANSFER',
            'status' =>
                'PENDING',
            'created_by_user_id' =>
                $workspace['user_id'],
            'created_at' =>
                now(),
            'updated_at' =>
                now(),
        ]);

        $this->actingAsWorkspace($workspace);

        $this->deleteJson(
            '/api/v1/finance/cash-accounts/'
            . $account
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'ENTITY_HAS_ACTIVITY'
            );
    }

    public function test_account_referenced_by_expense_cannot_be_deleted(): void
    {
        $workspace = $this->workspace(
            'cash-delete-expense@example.test',
            'Cash Delete Expense'
        );

        $this->insertAccount(
            $workspace,
            'Kas Default',
            'CASH',
            true
        );

        $account =
            $this->insertAccount(
                $workspace,
                'Kas Expense',
                'CASH',
                false
            );

        DB::table('expenses')->insert([
            'id' => (string) Str::ulid(),
            'tenant_id' =>
                $workspace['tenant_id'],
            'cash_account_id' =>
                $account,
            'amount' =>
                '50000.00',
            'currency' =>
                'IDR',
            'incurred_at' =>
                now(),
            'category' =>
                'Operasional',
            'description' =>
                'Expense test',
            'status' =>
                'DRAFT',
            'created_by_user_id' =>
                $workspace['user_id'],
            'created_at' =>
                now(),
            'updated_at' =>
                now(),
        ]);

        $this->actingAsWorkspace($workspace);

        $this->deleteJson(
            '/api/v1/finance/cash-accounts/'
            . $account
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'ENTITY_HAS_ACTIVITY'
            );
    }

    public function test_account_referenced_by_income_cannot_be_deleted(): void
    {
        $workspace = $this->workspace(
            'cash-delete-income@example.test',
            'Cash Delete Income'
        );

        $this->insertAccount(
            $workspace,
            'Kas Default',
            'CASH',
            true
        );

        $account =
            $this->insertAccount(
                $workspace,
                'Kas Income',
                'CASH',
                false
            );

        DB::table('incomes')->insert([
            'id' =>
                (string) Str::ulid(),

            'tenant_id' =>
                $workspace['tenant_id'],

            'cash_account_id' =>
                $account,

            'amount' =>
                '75000.00',

            'currency' =>
                'IDR',

            'occurred_at' =>
                now(),

            'category' =>
                'Lain-lain',

            'description' =>
                'Income test',

            'reference' =>
                null,

            'status' =>
                'DRAFT',

            'created_by_user_id' =>
                $workspace['user_id'],

            'posted_by_user_id' =>
                null,

            'posted_at' =>
                null,

            'voided_by_user_id' =>
                null,

            'voided_at' =>
                null,

            'void_reason' =>
                null,

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        $this->actingAsWorkspace(
            $workspace
        );

        $this->deleteJson(
            '/api/v1/finance/cash-accounts/'
            . $account
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'ENTITY_HAS_ACTIVITY'
            );

        $this->assertDatabaseHas(
            'cash_accounts',
            [
                'id' =>
                    $account,

                'tenant_id' =>
                    $workspace['tenant_id'],
            ]
        );

        $this->assertDatabaseHas(
            'incomes',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],

                'cash_account_id' =>
                    $account,

                'status' =>
                    'DRAFT',
            ]
        );
    }

    public function test_foreign_account_delete_returns_not_found(): void
    {
        $first = $this->workspace(
            'cash-delete-a@example.test',
            'Cash Delete A'
        );

        $second = $this->workspace(
            'cash-delete-b@example.test',
            'Cash Delete B'
        );

        $foreign =
            $this->insertAccount(
                $second,
                'Kas Foreign',
                'CASH',
                false
            );

        $this->actingAsWorkspace($first);

        $this->deleteJson(
            '/api/v1/finance/cash-accounts/'
            . $foreign
        )
            ->assertNotFound()
            ->assertJsonPath(
                'error.code',
                'RESOURCE_NOT_FOUND'
            );

        $this->assertDatabaseHas(
            'cash_accounts',
            [
                'id' => $foreign,
                'tenant_id' =>
                    $second['tenant_id'],
            ]
        );
    }

    public function test_cash_bank_capabilities_are_enforced(): void
    {
        $workspace = $this->workspace(
            'cash-capability@example.test',
            'Cash Capability'
        );

        $this->actingAsWorkspace($workspace);

        $this->denyCapability(
            $workspace,
            'finance.cash_bank.manage'
        );

        $this->postJson(
            '/api/v1/finance/cash-accounts',
            [
                'name' =>
                    'Tidak Boleh',
                'type' =>
                    'CASH',
            ]
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );

        $this->denyCapability(
            $workspace,
            'finance.cash_bank.view'
        );

        $this->getJson(
            '/api/v1/finance/cash-accounts'
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );
    }

    private function createAccount(
        string $name,
        string $type,
        array $extra = []
    ) {
        return $this->postJson(
            '/api/v1/finance/cash-accounts',
            [
                'name' => $name,
                'type' => $type,
                ...$extra,
            ]
        )->assertCreated();
    }

    private function insertAccount(
        array $workspace,
        string $name,
        string $type,
        bool $isDefault,
        ?string $bankName = 'Bank Test',
        ?string $accountNumber = '123456789',
        ?string $accountName = 'Signova Test',
        string $status = 'ACTIVE'
    ): string {
        $id = (string) Str::ulid();

        DB::table('cash_accounts')->insert([
            'id' => $id,
            'tenant_id' =>
                $workspace['tenant_id'],
            'name' => $name,
            'type' => $type,
            'bank_name' =>
                $type === 'BANK'
                    ? $bankName
                    : null,
            'account_number' =>
                $type === 'BANK'
                    ? $accountNumber
                    : null,
            'account_name' =>
                $type === 'BANK'
                    ? $accountName
                    : null,
            'currency' => 'IDR',
            'status' => $status,
            'is_default' =>
                $isDefault,
            'created_by_user_id' =>
                $workspace['user_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function cashTransaction(
        array $workspace,
        string $accountId,
        string $direction,
        string $amount
    ): void {
        DB::table('cash_transactions')->insert([
            'id' =>
                (string) Str::ulid(),
            'tenant_id' =>
                $workspace['tenant_id'],
            'cash_account_id' =>
                $accountId,
            'direction' =>
                $direction,
            'amount' =>
                $amount,
            'currency' =>
                'IDR',
            'occurred_at' =>
                now(),
            'source_type' =>
                'MANUAL_INCOME',
            'source_id' =>
                null,
            'reference' =>
                null,
            'description' =>
                'Test transaction',
            'reversal_of_transaction_id' =>
                null,
            'created_by_user_id' =>
                $workspace['user_id'],
            'created_at' =>
                now(),
            'updated_at' =>
                now(),
        ]);
    }

    private function insertCustomer(
        array $workspace
    ): string {
        $id =
            (string) Str::ulid();

        DB::table('customers')->insert([
            'id' => $id,
            'tenant_id' =>
                $workspace['tenant_id'],
            'business_id' =>
                $workspace['business_id'],
            'type' =>
                'COMPANY',
            'code' =>
                null,
            'name' =>
                'Customer Finance',
            'phone' =>
                null,
            'email' =>
                null,
            'tax_id' =>
                null,
            'payment_terms_days' =>
                0,
            'notes' =>
                null,
            'status' =>
                'ACTIVE',
            'created_at' =>
                now(),
            'updated_at' =>
                now(),
        ]);

        return $id;
    }

    private function workspace(
        string $email,
        string $businessName
    ): array {
        $workspace = app(
            CreateTenantWorkspaceAction::class
        )->execute([
            'name' =>
                'Owner',
            'email' =>
                $email,
            'password' =>
                'password',
            'tenant_name' =>
                $businessName,
            'timezone' =>
                'Asia/Jakarta',
        ]);

        return [
            ...$workspace,
            'user' =>
                User::query()->findOrFail(
                    $workspace['user_id']
                ),
        ];
    }

    private function actingAsWorkspace(
        array $workspace
    ): void {
        $this->actingAs(
            $workspace['user'],
            'sanctum'
        );

        $this->withHeader(
            'X-Tenant-ID',
            $workspace['tenant_id']
        );
    }

    private function denyCapability(
        array $workspace,
        string $capability
    ): void {
        $roleId =
            $workspace['owner_role_id'];

        $capabilityId =
            DB::table('capabilities')
                ->where(
                    'code',
                    $capability
                )
                ->value('id');

        $this->assertNotNull(
            $capabilityId,
            "Capability {$capability} harus tersedia."
        );

        DB::table(
            'role_capabilities'
        )->updateOrInsert(
            [
                'role_id' =>
                    $roleId,

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
