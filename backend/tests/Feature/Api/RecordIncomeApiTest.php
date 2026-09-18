<?php

namespace Tests\Feature\Api;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use App\Models\User;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecordIncomeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_record_posts_income_and_creates_one_cash_transaction(): void
    {
        $workspace =
            $this->workspace(
                'record-income@example.test',
                'Record Income'
            );

        $accountId =
            $this->insertAccount(
                $workspace,
                'Kas Utama',
                'ACTIVE'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $response =
            $this->postJson(
                '/api/v1/finance/incomes/actions/record',
                [
                    'cash_account_id' =>
                        $accountId,

                    'amount' =>
                        '125000.00',

                    'occurred_at' =>
                        '2026-09-19 09:00:00',

                    'category' =>
                        'Pendapatan Lainnya',

                    'description' =>
                        'Penjualan aset bekas',

                    'reference' =>
                        'INC-RECORD-001',
                ]
            )
                ->assertCreated()
                ->assertJsonPath(
                    'data.status',
                    'POSTED'
                )
                ->assertJsonPath(
                    'data.amount',
                    '125000.00'
                )
                ->assertJsonPath(
                    'data.cash_account.id',
                    $accountId
                );

        $incomeId =
            $response->json(
                'data.id'
            );

        $this->assertNotEmpty(
            $incomeId
        );

        $this->assertDatabaseHas(
            'incomes',
            [
                'id' =>
                    $incomeId,

                'tenant_id' =>
                    $workspace[
                        'tenant_id'
                    ],

                'business_id' =>
                    $workspace[
                        'business_id'
                    ],

                'cash_account_id' =>
                    $accountId,

                'status' =>
                    'POSTED',

                'amount' =>
                    '125000.00',

                'description' =>
                    'Penjualan aset bekas',
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
                    $accountId,

                'direction' =>
                    'IN',

                'amount' =>
                    '125000.00',

                'source_type' =>
                    'MANUAL_INCOME',

                'source_id' =>
                    $incomeId,

                'reference' =>
                    'INC-RECORD-001',
            ]
        );

        $transactionCount =
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
                ->where(
                    'source_type',
                    'MANUAL_INCOME'
                )
                ->where(
                    'source_id',
                    $incomeId
                )
                ->count();

        $this->assertSame(
            1,
            $transactionCount
        );

        /*
         * Bukti integrasi:
         * command yang sama langsung tersedia
         * pada unified income register.
         */
        $this->getJson(
            '/api/v1/finance/income-register'
            . '?group=MANUAL'
            . '&from=2026-09-19'
            . '&to=2026-09-19'
        )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.source.group',
                'MANUAL'
            )
            ->assertJsonPath(
                'data.0.source.type',
                'MANUAL_INCOME'
            )
            ->assertJsonPath(
                'data.0.amount',
                '125000.00'
            )
            ->assertJsonPath(
                'data.0.source.automatic',
                false
            );
    }

    public function test_record_rolls_back_income_when_posting_fails(): void
    {
        $workspace =
            $this->workspace(
                'record-income-rollback@example.test',
                'Record Income Rollback'
            );

        $inactiveAccountId =
            $this->insertAccount(
                $workspace,
                'Kas Nonaktif',
                'INACTIVE'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->postJson(
            '/api/v1/finance/incomes/actions/record',
            [
                'cash_account_id' =>
                    $inactiveAccountId,

                'amount' =>
                    '275000.00',

                'occurred_at' =>
                    '2026-09-19 10:00:00',

                'category' =>
                    'Pendapatan Lainnya',

                'description' =>
                    'Atomic rollback test',

                'reference' =>
                    'INC-ROLLBACK-001',
            ]
        )
            ->assertStatus(409);

        /*
         * create() sempat membuat DRAFT di dalam
         * transaction, tetapi kegagalan post()
         * harus membatalkan seluruh operation.
         */
        $this->assertDatabaseMissing(
            'incomes',
            [
                'tenant_id' =>
                    $workspace[
                        'tenant_id'
                    ],

                'business_id' =>
                    $workspace[
                        'business_id'
                    ],

                'description' =>
                    'Atomic rollback test',
            ]
        );

        $incomeCount =
            DB::table('incomes')
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

        $cashTransactionCount =
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

        $this->assertSame(
            0,
            $incomeCount
        );

        $this->assertSame(
            0,
            $cashTransactionCount
        );
    }

    public function test_record_requires_income_manage_capability(): void
    {
        $workspace =
            $this->workspace(
                'record-income-capability@example.test',
                'Record Income Capability'
            );

        $accountId =
            $this->insertAccount(
                $workspace,
                'Kas Utama',
                'ACTIVE'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->denyCapability(
            $workspace,
            'finance.income.manage'
        );

        $this->postJson(
            '/api/v1/finance/incomes/actions/record',
            [
                'cash_account_id' =>
                    $accountId,

                'amount' =>
                    '100000.00',

                'occurred_at' =>
                    '2026-09-19 11:00:00',

                'description' =>
                    'Forbidden income',
            ]
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );

        $this->assertDatabaseMissing(
            'incomes',
            [
                'tenant_id' =>
                    $workspace[
                        'tenant_id'
                    ],

                'business_id' =>
                    $workspace[
                        'business_id'
                    ],

                'description' =>
                    'Forbidden income',
            ]
        );
    }

    private function workspace(
        string $email,
        string $tenantName
    ): array {
        return app(
            CreateTenantWorkspaceAction::class
        )->execute([
            'name' =>
                'Record Income Owner',

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
                    $workspace[
                        'user_id'
                    ]
                );

        $this->actingAs(
            $user,
            'sanctum'
        );

        $this->withHeader(
            'X-Tenant-ID',
            $workspace[
                'tenant_id'
            ]
        );

        $this->withHeader(
            'X-Signova-Business',
            $workspace[
                'business_id'
            ]
        );
    }

    private function insertAccount(
        array $workspace,
        string $name,
        string $status
    ): string {
        $id =
            (string) Str::ulid();

        DB::table(
            'cash_accounts'
        )->insert([
            'id' =>
                $id,

            'tenant_id' =>
                $workspace[
                    'tenant_id'
                ],

            'business_id' =>
                $workspace[
                    'business_id'
                ],

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
                $workspace[
                    'user_id'
                ],

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
            DB::table(
                'capabilities'
            )
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
