<?php

namespace Tests\Feature\Api;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use App\Models\User;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class IncomeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_income_can_be_created_as_draft(): void
    {
        $workspace = $this->workspace(
            'income-create@example.test',
            'Income Create'
        );

        $account = $this->insertAccount(
            $workspace,
            'Kas Utama'
        );

        $this->actingAsWorkspace($workspace);

        $response = $this->postJson(
            '/api/v1/finance/incomes',
            [
                'cash_account_id' =>
                    $account,

                'amount' =>
                    '125000.00',

                'occurred_at' =>
                    now()->toISOString(),

                'category' =>
                    'Pendapatan Lain',

                'description' =>
                    'Pemasukan manual test',

                'reference' =>
                    'INC-001',
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.status',
                'DRAFT'
            )
            ->assertJsonPath(
                'data.amount',
                '125000.00'
            )
            ->assertJsonPath(
                'data.currency',
                'IDR'
            )
            ->assertJsonPath(
                'data.cash_account.id',
                $account
            );

        $incomeId =
            $response->json('data.id');

        $this->assertDatabaseHas(
            'incomes',
            [
                'id' =>
                    $incomeId,

                'tenant_id' =>
                    $workspace['tenant_id'],

                'cash_account_id' =>
                    $account,

                'status' =>
                    'DRAFT',

                'amount' =>
                    '125000.00',
            ]
        );

        $this->assertSame(
            0,
            DB::table('cash_transactions')
                ->where(
                    'source_id',
                    $incomeId
                )
                ->count()
        );
    }

    public function test_income_list_and_detail_are_tenant_scoped(): void
    {
        $first = $this->workspace(
            'income-scope-a@example.test',
            'Income Scope A'
        );

        $second = $this->workspace(
            'income-scope-b@example.test',
            'Income Scope B'
        );

        $firstAccount =
            $this->insertAccount(
                $first,
                'Kas A'
            );

        $secondAccount =
            $this->insertAccount(
                $second,
                'Kas B'
            );

        $firstIncome =
            $this->insertIncome(
                $first,
                $firstAccount,
                'Income Tenant A'
            );

        $secondIncome =
            $this->insertIncome(
                $second,
                $secondAccount,
                'Income Tenant B'
            );

        $this->actingAsWorkspace($first);

        $this->getJson(
            '/api/v1/finance/incomes'
        )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $firstIncome
            );

        $this->getJson(
            '/api/v1/finance/incomes/'
            . $secondIncome
        )
            ->assertNotFound()
            ->assertJsonPath(
                'error.code',
                'RESOURCE_NOT_FOUND'
            );
    }

    public function test_income_cannot_reference_foreign_cash_account(): void
    {
        $first = $this->workspace(
            'income-foreign-a@example.test',
            'Income Foreign A'
        );

        $second = $this->workspace(
            'income-foreign-b@example.test',
            'Income Foreign B'
        );

        $foreignAccount =
            $this->insertAccount(
                $second,
                'Kas Foreign'
            );

        $this->actingAsWorkspace($first);

        $this->postJson(
            '/api/v1/finance/incomes',
            [
                'cash_account_id' =>
                    $foreignAccount,

                'amount' =>
                    '50000.00',

                'occurred_at' =>
                    now()->toISOString(),

                'description' =>
                    'Foreign account',
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
                            'cash_account_id',
                        ],
                    ],
                ],
            ]);
    }

    public function test_draft_income_can_be_updated(): void
    {
        $workspace = $this->workspace(
            'income-update@example.test',
            'Income Update'
        );

        $account = $this->insertAccount(
            $workspace,
            'Kas Update'
        );

        $incomeId =
            $this->insertIncome(
                $workspace,
                $account,
                'Sebelum update'
            );

        $this->actingAsWorkspace($workspace);

        $this->patchJson(
            '/api/v1/finance/incomes/'
            . $incomeId,
            [
                'amount' =>
                    '275000.00',

                'description' =>
                    'Sesudah update',

                'reference' =>
                    'UPD-001',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'DRAFT'
            )
            ->assertJsonPath(
                'data.amount',
                '275000.00'
            )
            ->assertJsonPath(
                'data.description',
                'Sesudah update'
            );

        $this->assertDatabaseHas(
            'incomes',
            [
                'id' =>
                    $incomeId,

                'amount' =>
                    '275000.00',

                'description' =>
                    'Sesudah update',

                'status' =>
                    'DRAFT',
            ]
        );
    }

    public function test_unused_draft_income_can_be_deleted(): void
    {
        $workspace = $this->workspace(
            'income-delete@example.test',
            'Income Delete'
        );

        $account =
            $this->insertAccount(
                $workspace,
                'Kas Delete'
            );

        $incomeId =
            $this->insertIncome(
                $workspace,
                $account,
                'Delete me'
            );

        $this->actingAsWorkspace($workspace);

        $this->deleteJson(
            '/api/v1/finance/incomes/'
            . $incomeId
        )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $incomeId
            );

        $this->assertDatabaseMissing(
            'incomes',
            [
                'id' =>
                    $incomeId,
            ]
        );
    }

    public function test_draft_income_with_activity_cannot_be_deleted(): void
    {
        $workspace = $this->workspace(
            'income-delete-activity@example.test',
            'Income Delete Activity'
        );

        $account =
            $this->insertAccount(
                $workspace,
                'Kas Activity'
            );

        $incomeId =
            $this->insertIncome(
                $workspace,
                $account,
                'Has activity'
            );

        $this->insertLedger(
            $workspace,
            $account,
            'IN',
            '100000.00',
            'MANUAL_INCOME',
            $incomeId
        );

        $this->actingAsWorkspace($workspace);

        $this->deleteJson(
            '/api/v1/finance/incomes/'
            . $incomeId
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'ENTITY_HAS_ACTIVITY'
            );

        $this->assertDatabaseHas(
            'incomes',
            [
                'id' =>
                    $incomeId,
            ]
        );
    }

    public function test_post_requires_cash_account(): void
    {
        $workspace = $this->workspace(
            'income-post-account@example.test',
            'Income Post Account'
        );

        $incomeId =
            $this->insertIncome(
                $workspace,
                null,
                'No account'
            );

        $this->actingAsWorkspace($workspace);

        $this->postJson(
            '/api/v1/finance/incomes/'
            . $incomeId
            . '/actions/post'
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'ENTITY_STATE_CONFLICT'
            );

        $this->assertDatabaseHas(
            'incomes',
            [
                'id' =>
                    $incomeId,

                'status' =>
                    'DRAFT',
            ]
        );
    }

    public function test_post_requires_active_cash_account(): void
    {
        $workspace = $this->workspace(
            'income-post-active@example.test',
            'Income Post Active'
        );

        $account =
            $this->insertAccount(
                $workspace,
                'Kas Inactive',
                'INACTIVE'
            );

        $incomeId =
            $this->insertIncome(
                $workspace,
                $account,
                'Inactive account'
            );

        $this->actingAsWorkspace($workspace);

        $this->postJson(
            '/api/v1/finance/incomes/'
            . $incomeId
            . '/actions/post'
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'ENTITY_STATE_CONFLICT'
            );

        $this->assertSame(
            0,
            DB::table('cash_transactions')
                ->where(
                    'source_id',
                    $incomeId
                )
                ->count()
        );
    }

    public function test_post_creates_exactly_one_income_cash_transaction(): void
    {
        $workspace = $this->workspace(
            'income-post@example.test',
            'Income Post'
        );

        $account =
            $this->insertAccount(
                $workspace,
                'Kas Post'
            );

        $incomeId =
            $this->insertIncome(
                $workspace,
                $account,
                'Pendapatan jasa',
                '350000.00'
            );

        $this->actingAsWorkspace($workspace);

        $this->postJson(
            '/api/v1/finance/incomes/'
            . $incomeId
            . '/actions/post'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'POSTED'
            );

        $this->assertDatabaseHas(
            'incomes',
            [
                'id' =>
                    $incomeId,

                'status' =>
                    'POSTED',

                'posted_by_user_id' =>
                    $workspace['user_id'],
            ]
        );

        $this->assertDatabaseHas(
            'cash_transactions',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],

                'cash_account_id' =>
                    $account,

                'direction' =>
                    'IN',

                'amount' =>
                    '350000.00',

                'currency' =>
                    'IDR',

                'source_type' =>
                    'MANUAL_INCOME',

                'source_id' =>
                    $incomeId,

                'reversal_of_transaction_id' =>
                    null,
            ]
        );

        $this->assertSame(
            1,
            DB::table('cash_transactions')
                ->where(
                    'tenant_id',
                    $workspace['tenant_id']
                )
                ->where(
                    'source_type',
                    'MANUAL_INCOME'
                )
                ->where(
                    'source_id',
                    $incomeId
                )
                ->count()
        );
    }

    public function test_posted_income_cannot_be_posted_again_or_edited_or_deleted(): void
    {
        $workspace = $this->workspace(
            'income-posted-lock@example.test',
            'Income Posted Lock'
        );

        $account =
            $this->insertAccount(
                $workspace,
                'Kas Posted'
            );

        $incomeId =
            $this->insertIncome(
                $workspace,
                $account,
                'Posted lock'
            );

        $this->actingAsWorkspace($workspace);

        $this->postJson(
            '/api/v1/finance/incomes/'
            . $incomeId
            . '/actions/post'
        )->assertOk();

        $this->postJson(
            '/api/v1/finance/incomes/'
            . $incomeId
            . '/actions/post'
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'ENTITY_STATE_CONFLICT'
            );

        $this->patchJson(
            '/api/v1/finance/incomes/'
            . $incomeId,
            [
                'amount' =>
                    '999999.00',
            ]
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'ENTITY_STATE_CONFLICT'
            );

        $this->deleteJson(
            '/api/v1/finance/incomes/'
            . $incomeId
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'ENTITY_STATE_CONFLICT'
            );

        $this->assertSame(
            1,
            DB::table('cash_transactions')
                ->where(
                    'source_type',
                    'MANUAL_INCOME'
                )
                ->where(
                    'source_id',
                    $incomeId
                )
                ->count()
        );
    }

    public function test_void_requires_reason(): void
    {
        $workspace = $this->workspace(
            'income-void-reason@example.test',
            'Income Void Reason'
        );

        $account =
            $this->insertAccount(
                $workspace,
                'Kas Void Reason'
            );

        $incomeId =
            $this->insertIncome(
                $workspace,
                $account,
                'Void reason'
            );

        $this->actingAsWorkspace($workspace);

        $this->postJson(
            '/api/v1/finance/incomes/'
            . $incomeId
            . '/actions/post'
        )->assertOk();

        $this->postJson(
            '/api/v1/finance/incomes/'
            . $incomeId
            . '/actions/void',
            []
        )
            ->assertStatus(422)
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );

        $this->assertDatabaseHas(
            'incomes',
            [
                'id' =>
                    $incomeId,

                'status' =>
                    'POSTED',
            ]
        );
    }

    public function test_void_preserves_original_and_creates_compensating_out(): void
    {
        $workspace = $this->workspace(
            'income-void@example.test',
            'Income Void'
        );

        $account =
            $this->insertAccount(
                $workspace,
                'Kas Void'
            );

        $incomeId =
            $this->insertIncome(
                $workspace,
                $account,
                'Pendapatan dibatalkan',
                '450000.00'
            );

        $this->actingAsWorkspace($workspace);

        $this->postJson(
            '/api/v1/finance/incomes/'
            . $incomeId
            . '/actions/post'
        )->assertOk();

        $original =
            DB::table('cash_transactions')
                ->where(
                    'tenant_id',
                    $workspace['tenant_id']
                )
                ->where(
                    'source_type',
                    'MANUAL_INCOME'
                )
                ->where(
                    'source_id',
                    $incomeId
                )
                ->first();

        $this->assertNotNull(
            $original
        );

        $this->postJson(
            '/api/v1/finance/incomes/'
            . $incomeId
            . '/actions/void',
            [
                'reason' =>
                    'Salah pencatatan.',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'VOID'
            )
            ->assertJsonPath(
                'data.void_reason',
                'Salah pencatatan.'
            );

        $this->assertDatabaseHas(
            'cash_transactions',
            [
                'id' =>
                    $original->id,

                'direction' =>
                    'IN',

                'source_type' =>
                    'MANUAL_INCOME',

                'source_id' =>
                    $incomeId,
            ]
        );

        $this->assertDatabaseHas(
            'cash_transactions',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],

                'cash_account_id' =>
                    $account,

                'direction' =>
                    'OUT',

                'amount' =>
                    '450000.00',

                'currency' =>
                    'IDR',

                'source_type' =>
                    'MANUAL_INCOME_VOID',

                'source_id' =>
                    $incomeId,

                'reversal_of_transaction_id' =>
                    $original->id,
            ]
        );

        $this->assertSame(
            1,
            DB::table('cash_transactions')
                ->where(
                    'source_type',
                    'MANUAL_INCOME'
                )
                ->where(
                    'source_id',
                    $incomeId
                )
                ->count()
        );

        $this->assertSame(
            1,
            DB::table('cash_transactions')
                ->where(
                    'source_type',
                    'MANUAL_INCOME_VOID'
                )
                ->where(
                    'source_id',
                    $incomeId
                )
                ->count()
        );
    }

    public function test_voided_income_is_immutable_and_cannot_be_voided_again(): void
    {
        $workspace = $this->workspace(
            'income-void-lock@example.test',
            'Income Void Lock'
        );

        $account =
            $this->insertAccount(
                $workspace,
                'Kas Void Lock'
            );

        $incomeId =
            $this->insertIncome(
                $workspace,
                $account,
                'Void lock'
            );

        $this->actingAsWorkspace($workspace);

        $this->postJson(
            '/api/v1/finance/incomes/'
            . $incomeId
            . '/actions/post'
        )->assertOk();

        $this->postJson(
            '/api/v1/finance/incomes/'
            . $incomeId
            . '/actions/void',
            [
                'reason' =>
                    'Void pertama.',
            ]
        )->assertOk();

        $this->postJson(
            '/api/v1/finance/incomes/'
            . $incomeId
            . '/actions/void',
            [
                'reason' =>
                    'Void kedua.',
            ]
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'ENTITY_STATE_CONFLICT'
            );

        $this->patchJson(
            '/api/v1/finance/incomes/'
            . $incomeId,
            [
                'description' =>
                    'Tidak boleh berubah',
            ]
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'ENTITY_STATE_CONFLICT'
            );

        $this->deleteJson(
            '/api/v1/finance/incomes/'
            . $incomeId
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'ENTITY_STATE_CONFLICT'
            );

        $this->assertSame(
            1,
            DB::table('cash_transactions')
                ->where(
                    'source_type',
                    'MANUAL_INCOME_VOID'
                )
                ->where(
                    'source_id',
                    $incomeId
                )
                ->count()
        );
    }

    public function test_income_capabilities_are_enforced(): void
    {
        $workspace = $this->workspace(
            'income-capability@example.test',
            'Income Capability'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->denyCapability(
            $workspace,
            'finance.income.manage'
        );

        $this->postJson(
            '/api/v1/finance/incomes',
            [
                'amount' =>
                    '100000.00',

                'occurred_at' =>
                    now()->toISOString(),

                'description' =>
                    'Tidak boleh',
            ]
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );

        $this->denyCapability(
            $workspace,
            'finance.income.view'
        );

        $this->getJson(
            '/api/v1/finance/incomes'
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );
    }


    public function test_income_rejects_cash_account_from_other_business_in_same_tenant(): void
    {
        $workspace =
            $this->workspace(
                'income-business-scope@example.test',
                'Income Business Scope'
            );

        $otherBusiness =
            $this->secondBusinessWorkspace(
                $workspace,
                'Income Business B'
            );

        $foreignAccountId =
            $this->insertAccount(
                $otherBusiness,
                'Kas Income Business B'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->postJson(
            '/api/v1/finance/incomes',
            [
                'cash_account_id' =>
                    $foreignAccountId,

                'amount' =>
                    '50000.00',

                'occurred_at' =>
                    now()->toISOString(),

                'description' =>
                    'Tidak boleh lintas business',
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
                            'cash_account_id',
                        ],
                    ],
                ],
            ]);
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
                'Income API Owner',

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
        string $status = 'ACTIVE'
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
                $status,

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

    private function insertIncome(
        array $workspace,
        ?string $cashAccountId,
        string $description,
        string $amount = '100000.00',
        string $status = 'DRAFT'
    ): string {
        $id =
            (string) Str::ulid();

        DB::table(
            'incomes'
        )->insert([
            'id' =>
                $id,

            'tenant_id' =>
                $workspace['tenant_id'],

            'business_id' =>
                $workspace['business_id'],

            'cash_account_id' =>
                $cashAccountId,

            'amount' =>
                $amount,

            'currency' =>
                'IDR',

            'occurred_at' =>
                now(),

            'category' =>
                'Lain-lain',

            'description' =>
                $description,

            'reference' =>
                null,

            'status' =>
                $status,

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

        return $id;
    }

    private function insertLedger(
        array $workspace,
        string $cashAccountId,
        string $direction,
        string $amount,
        string $sourceType,
        ?string $sourceId
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
                now(),

            'source_type' =>
                $sourceType,

            'source_id' =>
                $sourceId,

            'reference' =>
                null,

            'description' =>
                'Income API ledger test',

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
