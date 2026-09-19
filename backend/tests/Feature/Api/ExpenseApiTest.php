<?php

namespace Tests\Feature\Api;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use App\Models\User;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExpenseApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_expense_can_be_recorded_atomically(): void
    {
        $workspace = $this->workspace(
            'expense-record@example.test',
            'Expense Record'
        );

        $account =
            $this->insertAccount(
                $workspace,
                'Kas Record'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $response =
            $this->postJson(
                '/api/v1/finance/expenses/actions/record',
                [
                    'cash_account_id' =>
                        $account,

                    'amount' =>
                        '175000.00',

                    'incurred_at' =>
                        now()->toISOString(),

                    'category' =>
                        'Operasional',

                    'description' =>
                        'Pengeluaran atomik',
                ]
            )
                ->assertCreated()
                ->assertJsonPath(
                    'data.status',
                    'POSTED'
                )
                ->assertJsonPath(
                    'data.amount',
                    '175000.00'
                )
                ->assertJsonPath(
                    'data.cash_account.id',
                    $account
                );

        $expenseId =
            $response->json(
                'data.id'
            );

        $this->assertDatabaseHas(
            'expenses',
            [
                'id' =>
                    $expenseId,

                'tenant_id' =>
                    $workspace[
                        'tenant_id'
                    ],

                'business_id' =>
                    $workspace[
                        'business_id'
                    ],

                'cash_account_id' =>
                    $account,

                'status' =>
                    'POSTED',

                'amount' =>
                    '175000.00',
            ]
        );

        $this->assertDatabaseHas(
            'cash_transactions',
            [
                'tenant_id' =>
                    $workspace[
                        'tenant_id'
                    ],

                'business_id' =>
                    $workspace[
                        'business_id'
                    ],

                'cash_account_id' =>
                    $account,

                'direction' =>
                    'OUT',

                'amount' =>
                    '175000.00',

                'currency' =>
                    'IDR',

                'source_type' =>
                    'EXPENSE',

                'source_id' =>
                    $expenseId,
            ]
        );
    }

    public function test_record_expense_rolls_back_when_account_is_inactive(): void
    {
        $workspace = $this->workspace(
            'expense-record-rollback@example.test',
            'Expense Record Rollback'
        );

        $account =
            $this->insertAccount(
                $workspace,
                'Kas Nonaktif',
                'INACTIVE'
            );

        $beforeExpenses =
            DB::table('expenses')
                ->where(
                    'tenant_id',
                    $workspace[
                        'tenant_id'
                    ]
                )
                ->where(
                    'business_id',
                    $workspace[
                        'business_id'
                    ]
                )
                ->count();

        $beforeTransactions =
            DB::table(
                'cash_transactions'
            )
                ->where(
                    'tenant_id',
                    $workspace[
                        'tenant_id'
                    ]
                )
                ->where(
                    'business_id',
                    $workspace[
                        'business_id'
                    ]
                )
                ->count();

        $this->actingAsWorkspace(
            $workspace
        );

        $this->postJson(
            '/api/v1/finance/expenses/actions/record',
            [
                'cash_account_id' =>
                    $account,

                'amount' =>
                    '90000.00',

                'incurred_at' =>
                    now()->toISOString(),

                'description' =>
                    'Harus rollback',
            ]
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'ENTITY_STATE_CONFLICT'
            );

        $this->assertSame(
            $beforeExpenses,
            DB::table('expenses')
                ->where(
                    'tenant_id',
                    $workspace[
                        'tenant_id'
                    ]
                )
                ->where(
                    'business_id',
                    $workspace[
                        'business_id'
                    ]
                )
                ->count()
        );

        $this->assertSame(
            $beforeTransactions,
            DB::table(
                'cash_transactions'
            )
                ->where(
                    'tenant_id',
                    $workspace[
                        'tenant_id'
                    ]
                )
                ->where(
                    'business_id',
                    $workspace[
                        'business_id'
                    ]
                )
                ->count()
        );
    }


    public function test_expense_can_be_created_as_draft(): void
    {
        $workspace = $this->workspace(
            'expense-create@example.test',
            'Expense Create'
        );

        $account = $this->insertAccount(
            $workspace,
            'Kas Utama'
        );

        $this->actingAsWorkspace($workspace);

        $response = $this->postJson(
            '/api/v1/finance/expenses',
            [
                'cash_account_id' =>
                    $account,

                'amount' =>
                    '125000.00',

                'incurred_at' =>
                    now()->toISOString(),

                'category' =>
                    'Operasional',

                'description' =>
                    'Pengeluaran test',
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

        $expenseId =
            $response->json('data.id');

        $this->assertDatabaseHas(
            'expenses',
            [
                'id' =>
                    $expenseId,

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
                    $expenseId
                )
                ->count()
        );
    }

    public function test_expense_list_and_detail_are_tenant_scoped(): void
    {
        $first = $this->workspace(
            'expense-scope-a@example.test',
            'Expense Scope A'
        );

        $second = $this->workspace(
            'expense-scope-b@example.test',
            'Expense Scope B'
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

        $firstExpense =
            $this->insertExpense(
                $first,
                $firstAccount,
                'Expense Tenant A'
            );

        $secondExpense =
            $this->insertExpense(
                $second,
                $secondAccount,
                'Expense Tenant B'
            );

        $this->actingAsWorkspace($first);

        $this->getJson(
            '/api/v1/finance/expenses'
        )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $firstExpense
            );

        $this->getJson(
            '/api/v1/finance/expenses/'
            . $secondExpense
        )
            ->assertNotFound()
            ->assertJsonPath(
                'error.code',
                'RESOURCE_NOT_FOUND'
            );
    }

    public function test_expense_cannot_reference_foreign_cash_account(): void
    {
        $first = $this->workspace(
            'expense-foreign-a@example.test',
            'Expense Foreign A'
        );

        $second = $this->workspace(
            'expense-foreign-b@example.test',
            'Expense Foreign B'
        );

        $foreignAccount =
            $this->insertAccount(
                $second,
                'Kas Foreign'
            );

        $this->actingAsWorkspace($first);

        $this->postJson(
            '/api/v1/finance/expenses',
            [
                'cash_account_id' =>
                    $foreignAccount,

                'amount' =>
                    '50000.00',

                'incurred_at' =>
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

    public function test_expense_list_can_be_filtered_by_period(): void
    {
        $workspace = $this->workspace(
            'expense-period@example.test',
            'Expense Period'
        );

        $account =
            $this->insertAccount(
                $workspace,
                'Kas Period'
            );

        $creatorId =
            User::query()
                ->where(
                    'email',
                    'expense-period@example.test'
                )
                ->value('id');

        $this->assertNotNull(
            $creatorId
        );

        DB::table('expenses')->insert([
            [
                'id' =>
                    (string) Str::ulid(),

                'tenant_id' =>
                    $workspace['tenant_id'],

                'business_id' =>
                    $workspace['business_id'],

                'cash_account_id' =>
                    $account,

                'amount' =>
                    '10000.00',

                'currency' =>
                    'IDR',

                'incurred_at' =>
                    '2026-08-31 10:00:00',

                'category' =>
                    'UAT',

                'description' =>
                    'Di luar periode',

                'status' =>
                    'DRAFT',

                'created_by_user_id' =>
                    $creatorId,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ],
            [
                'id' =>
                    (string) Str::ulid(),

                'tenant_id' =>
                    $workspace['tenant_id'],

                'business_id' =>
                    $workspace['business_id'],

                'cash_account_id' =>
                    $account,

                'amount' =>
                    '20000.00',

                'currency' =>
                    'IDR',

                'incurred_at' =>
                    '2026-09-10 10:00:00',

                'category' =>
                    'UAT',

                'description' =>
                    'Di dalam periode',

                'status' =>
                    'DRAFT',

                'created_by_user_id' =>
                    $creatorId,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ],
        ]);

        $this->actingAsWorkspace(
            $workspace
        );

        $this->getJson(
            '/api/v1/finance/expenses'
            . '?from=2026-09-01'
            . '&to=2026-09-30'
        )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.description',
                'Di dalam periode'
            );
    }

    public function test_expense_period_rejects_invalid_range(): void
    {
        $workspace = $this->workspace(
            'expense-period-invalid@example.test',
            'Expense Period Invalid'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->getJson(
            '/api/v1/finance/expenses'
            . '?from=2026-09-30'
            . '&to=2026-09-01'
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );
    }


    public function test_draft_expense_can_be_updated(): void
    {
        $workspace = $this->workspace(
            'expense-update@example.test',
            'Expense Update'
        );

        $account =
            $this->insertAccount(
                $workspace,
                'Kas Update'
            );

        $expenseId =
            $this->insertExpense(
                $workspace,
                $account,
                'Sebelum update'
            );

        $this->actingAsWorkspace($workspace);

        $this->patchJson(
            '/api/v1/finance/expenses/'
            . $expenseId,
            [
                'amount' =>
                    '275000.00',

                'description' =>
                    'Sesudah update',

                'category' =>
                    'Transportasi',
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
            'expenses',
            [
                'id' =>
                    $expenseId,

                'amount' =>
                    '275000.00',

                'description' =>
                    'Sesudah update',

                'status' =>
                    'DRAFT',
            ]
        );
    }

    public function test_unused_draft_expense_can_be_deleted(): void
    {
        $workspace = $this->workspace(
            'expense-delete@example.test',
            'Expense Delete'
        );

        $account =
            $this->insertAccount(
                $workspace,
                'Kas Delete'
            );

        $expenseId =
            $this->insertExpense(
                $workspace,
                $account,
                'Delete me'
            );

        $this->actingAsWorkspace($workspace);

        $this->deleteJson(
            '/api/v1/finance/expenses/'
            . $expenseId
        )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $expenseId
            );

        $this->assertDatabaseMissing(
            'expenses',
            [
                'id' =>
                    $expenseId,
            ]
        );
    }

    public function test_draft_expense_with_activity_cannot_be_deleted(): void
    {
        $workspace = $this->workspace(
            'expense-delete-activity@example.test',
            'Expense Delete Activity'
        );

        $account =
            $this->insertAccount(
                $workspace,
                'Kas Activity'
            );

        $expenseId =
            $this->insertExpense(
                $workspace,
                $account,
                'Has activity'
            );

        $this->insertLedger(
            $workspace,
            $account,
            'OUT',
            '100000.00',
            'EXPENSE',
            $expenseId
        );

        $this->actingAsWorkspace($workspace);

        $this->deleteJson(
            '/api/v1/finance/expenses/'
            . $expenseId
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'ENTITY_HAS_ACTIVITY'
            );
    }

    public function test_post_requires_cash_account(): void
    {
        $workspace = $this->workspace(
            'expense-post-account@example.test',
            'Expense Post Account'
        );

        $expenseId =
            $this->insertExpense(
                $workspace,
                null,
                'No account'
            );

        $this->actingAsWorkspace($workspace);

        $this->postJson(
            '/api/v1/finance/expenses/'
            . $expenseId
            . '/actions/post'
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'ENTITY_STATE_CONFLICT'
            );
    }

    public function test_post_requires_active_cash_account(): void
    {
        $workspace = $this->workspace(
            'expense-post-active@example.test',
            'Expense Post Active'
        );

        $account =
            $this->insertAccount(
                $workspace,
                'Kas Inactive',
                'INACTIVE'
            );

        $expenseId =
            $this->insertExpense(
                $workspace,
                $account,
                'Inactive account'
            );

        $this->actingAsWorkspace($workspace);

        $this->postJson(
            '/api/v1/finance/expenses/'
            . $expenseId
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
                    $expenseId
                )
                ->count()
        );
    }

    public function test_post_creates_exactly_one_expense_cash_transaction(): void
    {
        $workspace = $this->workspace(
            'expense-post@example.test',
            'Expense Post'
        );

        $account =
            $this->insertAccount(
                $workspace,
                'Kas Post'
            );

        $expenseId =
            $this->insertExpense(
                $workspace,
                $account,
                'Biaya operasional',
                '350000.00'
            );

        $this->actingAsWorkspace($workspace);

        $this->postJson(
            '/api/v1/finance/expenses/'
            . $expenseId
            . '/actions/post'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'POSTED'
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
                    '350000.00',

                'currency' =>
                    'IDR',

                'source_type' =>
                    'EXPENSE',

                'source_id' =>
                    $expenseId,

                'reversal_of_transaction_id' =>
                    null,
            ]
        );

        $this->assertSame(
            1,
            DB::table('cash_transactions')
                ->where(
                    'source_type',
                    'EXPENSE'
                )
                ->where(
                    'source_id',
                    $expenseId
                )
                ->count()
        );
    }

    public function test_posted_expense_cannot_be_posted_again_or_edited_or_deleted(): void
    {
        $workspace = $this->workspace(
            'expense-posted-lock@example.test',
            'Expense Posted Lock'
        );

        $account =
            $this->insertAccount(
                $workspace,
                'Kas Posted'
            );

        $expenseId =
            $this->insertExpense(
                $workspace,
                $account,
                'Posted lock'
            );

        $this->actingAsWorkspace($workspace);

        $this->postJson(
            '/api/v1/finance/expenses/'
            . $expenseId
            . '/actions/post'
        )->assertOk();

        $this->postJson(
            '/api/v1/finance/expenses/'
            . $expenseId
            . '/actions/post'
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'ENTITY_STATE_CONFLICT'
            );

        $this->patchJson(
            '/api/v1/finance/expenses/'
            . $expenseId,
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
            '/api/v1/finance/expenses/'
            . $expenseId
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'ENTITY_HAS_ACTIVITY'
            );
    }

    public function test_void_requires_reason(): void
    {
        $workspace = $this->workspace(
            'expense-void-reason@example.test',
            'Expense Void Reason'
        );

        $account =
            $this->insertAccount(
                $workspace,
                'Kas Void Reason'
            );

        $expenseId =
            $this->insertExpense(
                $workspace,
                $account,
                'Void reason'
            );

        $this->actingAsWorkspace($workspace);

        $this->postJson(
            '/api/v1/finance/expenses/'
            . $expenseId
            . '/actions/post'
        )->assertOk();

        $this->postJson(
            '/api/v1/finance/expenses/'
            . $expenseId
            . '/actions/void',
            []
        )
            ->assertStatus(422)
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );
    }

    public function test_void_preserves_original_and_creates_compensating_in(): void
    {
        $workspace = $this->workspace(
            'expense-void@example.test',
            'Expense Void'
        );

        $account =
            $this->insertAccount(
                $workspace,
                'Kas Void'
            );

        $expenseId =
            $this->insertExpense(
                $workspace,
                $account,
                'Biaya dibatalkan',
                '450000.00'
            );

        $this->actingAsWorkspace($workspace);

        $this->postJson(
            '/api/v1/finance/expenses/'
            . $expenseId
            . '/actions/post'
        )->assertOk();

        $original =
            DB::table('cash_transactions')
                ->where(
                    'source_type',
                    'EXPENSE'
                )
                ->where(
                    'source_id',
                    $expenseId
                )
                ->first();

        $this->assertNotNull(
            $original
        );

        $this->postJson(
            '/api/v1/finance/expenses/'
            . $expenseId
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
                    'OUT',

                'source_type' =>
                    'EXPENSE',

                'source_id' =>
                    $expenseId,
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
                    '450000.00',

                'currency' =>
                    'IDR',

                'source_type' =>
                    'EXPENSE_VOID',

                'source_id' =>
                    $expenseId,

                'reversal_of_transaction_id' =>
                    $original->id,
            ]
        );

        $this->assertSame(
            1,
            DB::table('cash_transactions')
                ->where(
                    'source_type',
                    'EXPENSE_VOID'
                )
                ->where(
                    'source_id',
                    $expenseId
                )
                ->count()
        );
    }

    public function test_voided_expense_is_immutable_and_cannot_be_voided_again(): void
    {
        $workspace = $this->workspace(
            'expense-void-lock@example.test',
            'Expense Void Lock'
        );

        $account =
            $this->insertAccount(
                $workspace,
                'Kas Void Lock'
            );

        $expenseId =
            $this->insertExpense(
                $workspace,
                $account,
                'Void lock'
            );

        $this->actingAsWorkspace($workspace);

        $this->postJson(
            '/api/v1/finance/expenses/'
            . $expenseId
            . '/actions/post'
        )->assertOk();

        $this->postJson(
            '/api/v1/finance/expenses/'
            . $expenseId
            . '/actions/void',
            [
                'reason' =>
                    'Void pertama.',
            ]
        )->assertOk();

        $this->postJson(
            '/api/v1/finance/expenses/'
            . $expenseId
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
            '/api/v1/finance/expenses/'
            . $expenseId,
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
            '/api/v1/finance/expenses/'
            . $expenseId
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'ENTITY_HAS_ACTIVITY'
            );

        $this->assertSame(
            1,
            DB::table('cash_transactions')
                ->where(
                    'source_type',
                    'EXPENSE_VOID'
                )
                ->where(
                    'source_id',
                    $expenseId
                )
                ->count()
        );
    }

    public function test_expense_capabilities_are_enforced(): void
    {
        $workspace = $this->workspace(
            'expense-capability@example.test',
            'Expense Capability'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->denyCapability(
            $workspace,
            'finance.expense.manage'
        );

        $this->postJson(
            '/api/v1/finance/expenses',
            [
                'amount' =>
                    '100000.00',

                'incurred_at' =>
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
            'finance.expense.view'
        );

        $this->getJson(
            '/api/v1/finance/expenses'
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );
    }


    public function test_expense_rejects_cash_account_from_other_business_in_same_tenant(): void
    {
        $workspace =
            $this->workspace(
                'expense-business-scope@example.test',
                'Expense Business Scope'
            );

        $otherBusiness =
            $this->secondBusinessWorkspace(
                $workspace,
                'Expense Business B'
            );

        $foreignAccountId =
            $this->insertAccount(
                $otherBusiness,
                'Kas Expense Business B'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->postJson(
            '/api/v1/finance/expenses',
            [
                'cash_account_id' =>
                    $foreignAccountId,

                'amount' =>
                    '50000.00',

                'incurred_at' =>
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


    public function test_manage_user_submits_expense_without_moving_cash(): void
    {
        $workspace = $this->workspace(
            'expense-submit@example.test',
            'Expense Submit'
        );

        $account =
            $this->insertAccount(
                $workspace,
                'Kas Submit'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->denyCapability(
            $workspace,
            'finance.expense.approve'
        );

        $response =
            $this->postJson(
                '/api/v1/finance/expenses',
                [
                    'cash_account_id' =>
                        $account,

                    'amount' =>
                        '125000.00',

                    'incurred_at' =>
                        now()->toISOString(),

                    'category' =>
                        'Biaya Operasional Umum',

                    'description' =>
                        'Menunggu approval',
                ]
            )->assertCreated();

        $expenseId =
            $response->json('data.id');

        $this->postJson(
            '/api/v1/finance/expenses/'
            . $expenseId
            . '/actions/submit'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'PENDING_APPROVAL'
            );

        $this->assertDatabaseHas(
            'expense_status_history',
            [
                'expense_id' =>
                    $expenseId,

                'from_status' =>
                    'DRAFT',

                'to_status' =>
                    'PENDING_APPROVAL',

                'action' =>
                    'SUBMITTED',
            ]
        );

        $this->assertSame(
            0,
            DB::table('cash_transactions')
                ->where(
                    'source_type',
                    'EXPENSE'
                )
                ->where(
                    'source_id',
                    $expenseId
                )
                ->count()
        );
    }

    public function test_approver_can_approve_pending_expense_once(): void
    {
        $workspace = $this->workspace(
            'expense-approve@example.test',
            'Expense Approve'
        );

        $account =
            $this->insertAccount(
                $workspace,
                'Kas Approve'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->denyCapability(
            $workspace,
            'finance.expense.approve'
        );

        $response =
            $this->postJson(
                '/api/v1/finance/expenses',
                [
                    'cash_account_id' =>
                        $account,

                    'amount' =>
                        '250000.00',

                    'incurred_at' =>
                        now()->toISOString(),

                    'description' =>
                        'Approval expense',
                ]
            )->assertCreated();

        $expenseId =
            $response->json('data.id');

        $this->postJson(
            '/api/v1/finance/expenses/'
            . $expenseId
            . '/actions/submit'
        )->assertOk();

        $this->allowCapability(
            $workspace,
            'finance.expense.approve'
        );

        $this->postJson(
            '/api/v1/finance/expenses/'
            . $expenseId
            . '/actions/approve'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'POSTED'
            );

        $this->assertDatabaseHas(
            'expenses',
            [
                'id' =>
                    $expenseId,

                'status' =>
                    'POSTED',

                'approved_by_user_id' =>
                    $workspace['user_id'],

                'posted_by_user_id' =>
                    $workspace['user_id'],
            ]
        );

        $this->assertSame(
            1,
            DB::table('cash_transactions')
                ->where(
                    'source_type',
                    'EXPENSE'
                )
                ->where(
                    'source_id',
                    $expenseId
                )
                ->where(
                    'direction',
                    'OUT'
                )
                ->count()
        );

        $this->assertDatabaseHas(
            'expense_status_history',
            [
                'expense_id' =>
                    $expenseId,

                'action' =>
                    'APPROVED',

                'from_status' =>
                    'PENDING_APPROVAL',

                'to_status' =>
                    'POSTED',
            ]
        );

        $this->postJson(
            '/api/v1/finance/expenses/'
            . $expenseId
            . '/actions/approve'
        )
            ->assertStatus(409);

        $this->assertSame(
            1,
            DB::table('cash_transactions')
                ->where(
                    'source_type',
                    'EXPENSE'
                )
                ->where(
                    'source_id',
                    $expenseId
                )
                ->count()
        );
    }

    public function test_rejected_expense_can_be_revised_to_draft(): void
    {
        $workspace = $this->workspace(
            'expense-reject@example.test',
            'Expense Reject'
        );

        $account =
            $this->insertAccount(
                $workspace,
                'Kas Reject'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $response =
            $this->postJson(
                '/api/v1/finance/expenses',
                [
                    'cash_account_id' =>
                        $account,

                    'amount' =>
                        '80000.00',

                    'incurred_at' =>
                        now()->toISOString(),

                    'description' =>
                        'Expense untuk ditolak',
                ]
            )->assertCreated();

        $expenseId =
            $response->json('data.id');

        $this->postJson(
            '/api/v1/finance/expenses/'
            . $expenseId
            . '/actions/submit'
        )->assertOk();

        $this->postJson(
            '/api/v1/finance/expenses/'
            . $expenseId
            . '/actions/reject',
            [
                'reason' =>
                    'Bukti belum lengkap',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'REJECTED'
            )
            ->assertJsonPath(
                'data.rejection_reason',
                'Bukti belum lengkap'
            );

        $this->assertSame(
            0,
            DB::table('cash_transactions')
                ->where(
                    'source_id',
                    $expenseId
                )
                ->count()
        );

        $this->postJson(
            '/api/v1/finance/expenses/'
            . $expenseId
            . '/actions/revise'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'DRAFT'
            )
            ->assertJsonPath(
                'data.rejection_reason',
                null
            );

        $this->assertDatabaseHas(
            'expense_status_history',
            [
                'expense_id' =>
                    $expenseId,

                'action' =>
                    'REJECTED',
            ]
        );

        $this->assertDatabaseHas(
            'expense_status_history',
            [
                'expense_id' =>
                    $expenseId,

                'action' =>
                    'REVISED',
            ]
        );
    }

    public function test_direct_record_requires_expense_approve_capability(): void
    {
        $workspace = $this->workspace(
            'expense-direct-cap@example.test',
            'Expense Direct Capability'
        );

        $account =
            $this->insertAccount(
                $workspace,
                'Kas Direct Capability'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->denyCapability(
            $workspace,
            'finance.expense.approve'
        );

        $this->postJson(
            '/api/v1/finance/expenses/actions/record',
            [
                'cash_account_id' =>
                    $account,

                'amount' =>
                    '50000.00',

                'incurred_at' =>
                    now()->toISOString(),

                'description' =>
                    'Tidak boleh direct post',
            ]
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
                'Expense API Owner',

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

    private function insertExpense(
        array $workspace,
        ?string $cashAccountId,
        string $description,
        string $amount = '100000.00'
    ): string {
        $id =
            (string) Str::ulid();

        DB::table(
            'expenses'
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

            'incurred_at' =>
                now(),

            'category' =>
                'Operasional',

            'description' =>
                $description,

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
                'Expense API ledger test',

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

    private function allowCapability(
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
                    'ALLOW',

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]
        );
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
