<?php

namespace Tests\Feature\Tenancy;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinanceFoundationIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_cash_transaction_account_must_belong_to_same_tenant(): void
    {
        $first = $this->workspace(
            'finance-tx-a@example.test',
            'Finance TX A'
        );

        $second = $this->workspace(
            'finance-tx-b@example.test',
            'Finance TX B'
        );

        $foreignAccount = $this->cashAccount(
            $second,
            'Kas Tenant B'
        );

        $this->expectException(
            QueryException::class
        );

        $this->cashTransaction(
            $first,
            $foreignAccount
        );
    }

    public function test_expense_account_must_belong_to_same_tenant(): void
    {
        $first = $this->workspace(
            'finance-expense-a@example.test',
            'Finance Expense A'
        );

        $second = $this->workspace(
            'finance-expense-b@example.test',
            'Finance Expense B'
        );

        $foreignAccount = $this->cashAccount(
            $second,
            'Kas Tenant B'
        );

        $this->expectException(
            QueryException::class
        );

        $this->expense(
            $first,
            $foreignAccount
        );
    }

    public function test_cash_account_actor_must_belong_to_same_tenant(): void
    {
        $first = $this->workspace(
            'finance-account-actor-a@example.test',
            'Finance Account Actor A'
        );

        $second = $this->workspace(
            'finance-account-actor-b@example.test',
            'Finance Account Actor B'
        );

        $this->expectException(
            QueryException::class
        );

        DB::table('cash_accounts')->insert([
            'id' =>
                (string) Str::ulid(),

            'tenant_id' =>
                $first['tenant_id'],

            'name' =>
                'Kas Invalid Actor',

            'type' =>
                'CASH',

            'currency' =>
                'IDR',

            'status' =>
                'ACTIVE',

            'is_default' =>
                false,

            'created_by_user_id' =>
                $second['user_id'],

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);
    }

    public function test_cash_transaction_actor_must_belong_to_same_tenant(): void
    {
        $first = $this->workspace(
            'finance-tx-actor-a@example.test',
            'Finance TX Actor A'
        );

        $second = $this->workspace(
            'finance-tx-actor-b@example.test',
            'Finance TX Actor B'
        );

        $account = $this->cashAccount(
            $first,
            'Kas Tenant A'
        );

        $this->expectException(
            QueryException::class
        );

        $this->cashTransaction(
            $first,
            $account,
            '10000.00',
            'IN',
            'MANUAL_INCOME',
            null,
            null,
            $second['user_id']
        );
    }

    public function test_cash_transaction_amount_must_be_positive(): void
    {
        $workspace = $this->workspace(
            'finance-tx-amount@example.test',
            'Finance TX Amount'
        );

        $account = $this->cashAccount(
            $workspace,
            'Kas Utama'
        );

        $this->expectException(
            QueryException::class
        );

        $this->cashTransaction(
            $workspace,
            $account,
            '0.00'
        );
    }

    public function test_expense_amount_must_be_positive(): void
    {
        $workspace = $this->workspace(
            'finance-expense-amount@example.test',
            'Finance Expense Amount'
        );

        $account = $this->cashAccount(
            $workspace,
            'Kas Utama'
        );

        $this->expectException(
            QueryException::class
        );

        $this->expense(
            $workspace,
            $account,
            '0.00'
        );
    }

    public function test_cash_transaction_direction_must_be_canonical(): void
    {
        $workspace = $this->workspace(
            'finance-direction@example.test',
            'Finance Direction'
        );

        $account = $this->cashAccount(
            $workspace,
            'Kas Utama'
        );

        $this->expectException(
            QueryException::class
        );

        $this->cashTransaction(
            $workspace,
            $account,
            '10000.00',
            'SIDEWAYS'
        );
    }

    public function test_cash_account_type_must_be_canonical(): void
    {
        $workspace = $this->workspace(
            'finance-account-type@example.test',
            'Finance Account Type'
        );

        $this->expectException(
            QueryException::class
        );

        $this->cashAccount(
            $workspace,
            'Dompet Invalid',
            'EWALLET'
        );
    }

    public function test_only_one_default_cash_account_per_tenant(): void
    {
        $workspace = $this->workspace(
            'finance-default@example.test',
            'Finance Default'
        );

        $this->cashAccount(
            $workspace,
            'Kas Default 1',
            'CASH',
            true
        );

        $this->expectException(
            QueryException::class
        );

        $this->cashAccount(
            $workspace,
            'Kas Default 2',
            'BANK',
            true
        );
    }

    public function test_cash_transaction_source_is_unique_inside_tenant(): void
    {
        $workspace = $this->workspace(
            'finance-source@example.test',
            'Finance Source'
        );

        $account = $this->cashAccount(
            $workspace,
            'Kas Utama'
        );

        $sourceId =
            (string) Str::ulid();

        $this->cashTransaction(
            $workspace,
            $account,
            '10000.00',
            'IN',
            'PAYMENT',
            $sourceId
        );

        $this->expectException(
            QueryException::class
        );

        $this->cashTransaction(
            $workspace,
            $account,
            '10000.00',
            'IN',
            'PAYMENT',
            $sourceId
        );
    }

    public function test_same_cash_transaction_source_is_allowed_across_tenants(): void
    {
        $first = $this->workspace(
            'finance-source-a@example.test',
            'Finance Source A'
        );

        $second = $this->workspace(
            'finance-source-b@example.test',
            'Finance Source B'
        );

        $firstAccount = $this->cashAccount(
            $first,
            'Kas A'
        );

        $secondAccount = $this->cashAccount(
            $second,
            'Kas B'
        );

        $sourceId =
            (string) Str::ulid();

        $this->cashTransaction(
            $first,
            $firstAccount,
            '10000.00',
            'IN',
            'PAYMENT',
            $sourceId
        );

        $this->cashTransaction(
            $second,
            $secondAccount,
            '10000.00',
            'IN',
            'PAYMENT',
            $sourceId
        );

        $this->assertDatabaseCount(
            'cash_transactions',
            2
        );
    }

    public function test_manual_transactions_can_have_null_source_id(): void
    {
        $workspace = $this->workspace(
            'finance-manual-source@example.test',
            'Finance Manual Source'
        );

        $account = $this->cashAccount(
            $workspace,
            'Kas Utama'
        );

        $this->cashTransaction(
            $workspace,
            $account,
            '10000.00'
        );

        $this->cashTransaction(
            $workspace,
            $account,
            '20000.00'
        );

        $this->assertDatabaseCount(
            'cash_transactions',
            2
        );
    }

    public function test_cash_transaction_reversal_cannot_cross_tenants(): void
    {
        $first = $this->workspace(
            'finance-reversal-a@example.test',
            'Finance Reversal A'
        );

        $second = $this->workspace(
            'finance-reversal-b@example.test',
            'Finance Reversal B'
        );

        $firstAccount = $this->cashAccount(
            $first,
            'Kas A'
        );

        $secondAccount = $this->cashAccount(
            $second,
            'Kas B'
        );

        $foreignTransaction =
            $this->cashTransaction(
                $second,
                $secondAccount,
                '10000.00'
            );

        $this->expectException(
            QueryException::class
        );

        $this->cashTransaction(
            $first,
            $firstAccount,
            '10000.00',
            'OUT',
            'EXPENSE_VOID',
            (string) Str::ulid(),
            $foreignTransaction
        );
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
            'user_id' =>
                $workspace['user_id'],

            'tenant_id' =>
                $workspace['tenant_id'],
        ];
    }

    private function cashAccount(
        array $workspace,
        string $name,
        string $type = 'CASH',
        bool $isDefault = false
    ): string {
        $id = (string) Str::ulid();

        DB::table('cash_accounts')->insert([
            'id' =>
                $id,

            'tenant_id' =>
                $workspace['tenant_id'],

            'name' =>
                $name,

            'type' =>
                $type,

            'currency' =>
                'IDR',

            'status' =>
                'ACTIVE',

            'is_default' =>
                $isDefault,

            'created_by_user_id' =>
                $workspace['user_id'],

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        return $id;
    }

    private function cashTransaction(
        array $workspace,
        string $cashAccountId,
        string $amount = '10000.00',
        string $direction = 'IN',
        string $sourceType = 'MANUAL_INCOME',
        ?string $sourceId = null,
        ?string $reversalOfTransactionId = null,
        ?string $createdByUserId = null
    ): string {
        $id = (string) Str::ulid();

        DB::table('cash_transactions')->insert([
            'id' =>
                $id,

            'tenant_id' =>
                $workspace['tenant_id'],

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
                null,

            'reversal_of_transaction_id' =>
                $reversalOfTransactionId,

            'created_by_user_id' =>
                $createdByUserId
                    ?? $workspace['user_id'],

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        return $id;
    }

    private function expense(
        array $workspace,
        ?string $cashAccountId,
        string $amount = '10000.00'
    ): string {
        $id = (string) Str::ulid();

        DB::table('expenses')->insert([
            'id' =>
                $id,

            'tenant_id' =>
                $workspace['tenant_id'],

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
                'Biaya operasional',

            'status' =>
                'DRAFT',

            'created_by_user_id' =>
                $workspace['user_id'],

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        return $id;
    }
}
