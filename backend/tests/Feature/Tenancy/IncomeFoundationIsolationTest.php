<?php

namespace Tests\Feature\Tenancy;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class IncomeFoundationIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_income_cash_account_must_belong_to_same_tenant(): void
    {
        $first = $this->workspace(
            'income-account-a@example.test',
            'Income Account A'
        );

        $second = $this->workspace(
            'income-account-b@example.test',
            'Income Account B'
        );

        $foreignAccount =
            $this->cashAccount(
                $second
            );

        $this->expectException(
            QueryException::class
        );

        $this->income(
            $first,
            $foreignAccount
        );
    }

    public function test_income_created_actor_must_belong_to_same_tenant(): void
    {
        $first = $this->workspace(
            'income-actor-a@example.test',
            'Income Actor A'
        );

        $second = $this->workspace(
            'income-actor-b@example.test',
            'Income Actor B'
        );

        $account =
            $this->cashAccount(
                $first
            );

        $this->expectException(
            QueryException::class
        );

        $this->income(
            $first,
            $account,
            '100000.00',
            'IDR',
            'DRAFT',
            $second['user_id']
        );
    }

    public function test_income_posted_actor_must_belong_to_same_tenant(): void
    {
        $first = $this->workspace(
            'income-posted-a@example.test',
            'Income Posted A'
        );

        $second = $this->workspace(
            'income-posted-b@example.test',
            'Income Posted B'
        );

        $account =
            $this->cashAccount(
                $first
            );

        $this->expectException(
            QueryException::class
        );

        $this->income(
            $first,
            $account,
            '100000.00',
            'IDR',
            'POSTED',
            $first['user_id'],
            $second['user_id'],
            null
        );
    }

    public function test_income_void_actor_must_belong_to_same_tenant(): void
    {
        $first = $this->workspace(
            'income-void-a@example.test',
            'Income Void A'
        );

        $second = $this->workspace(
            'income-void-b@example.test',
            'Income Void B'
        );

        $account =
            $this->cashAccount(
                $first
            );

        $this->expectException(
            QueryException::class
        );

        $this->income(
            $first,
            $account,
            '100000.00',
            'IDR',
            'VOID',
            $first['user_id'],
            $first['user_id'],
            $second['user_id']
        );
    }

    public function test_income_amount_must_be_positive(): void
    {
        $workspace = $this->workspace(
            'income-amount@example.test',
            'Income Amount'
        );

        $account =
            $this->cashAccount(
                $workspace
            );

        $this->expectException(
            QueryException::class
        );

        $this->income(
            $workspace,
            $account,
            '0.00'
        );
    }

    public function test_income_currency_must_be_idr(): void
    {
        $workspace = $this->workspace(
            'income-currency@example.test',
            'Income Currency'
        );

        $account =
            $this->cashAccount(
                $workspace
            );

        $this->expectException(
            QueryException::class
        );

        $this->income(
            $workspace,
            $account,
            '100000.00',
            'USD'
        );
    }

    public function test_income_status_must_be_canonical(): void
    {
        $workspace = $this->workspace(
            'income-status@example.test',
            'Income Status'
        );

        $account =
            $this->cashAccount(
                $workspace
            );

        $this->expectException(
            QueryException::class
        );

        $this->income(
            $workspace,
            $account,
            '100000.00',
            'IDR',
            'PAID'
        );
    }

    public function test_income_draft_may_have_no_cash_account(): void
    {
        $workspace = $this->workspace(
            'income-draft-null@example.test',
            'Income Draft Null'
        );

        $incomeId =
            $this->income(
                $workspace,
                null
            );

        $this->assertDatabaseHas(
            'incomes',
            [
                'id' =>
                    $incomeId,

                'tenant_id' =>
                    $workspace['tenant_id'],

                'cash_account_id' =>
                    null,

                'status' =>
                    'DRAFT',
            ]
        );
    }

    public function test_manual_income_void_is_valid_cash_transaction_source(): void
    {
        $workspace = $this->workspace(
            'income-void-source@example.test',
            'Income Void Source'
        );

        $account =
            $this->cashAccount(
                $workspace
            );

        $incomeId =
            $this->income(
                $workspace,
                $account,
                '100000.00',
                'IDR',
                'POSTED',
                $workspace['user_id'],
                $workspace['user_id'],
                null
            );

        $originalId =
            (string) Str::ulid();

        DB::table(
            'cash_transactions'
        )->insert([
            'id' =>
                $originalId,

            'tenant_id' =>
                $workspace['tenant_id'],

            'cash_account_id' =>
                $account,

            'direction' =>
                'IN',

            'amount' =>
                '100000.00',

            'currency' =>
                'IDR',

            'occurred_at' =>
                now(),

            'source_type' =>
                'MANUAL_INCOME',

            'source_id' =>
                $incomeId,

            'reference' =>
                null,

            'description' =>
                'Pemasukan manual.',

            'reversal_of_transaction_id' =>
                null,

            'created_by_user_id' =>
                $workspace['user_id'],

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        DB::table(
            'cash_transactions'
        )->insert([
            'id' =>
                (string) Str::ulid(),

            'tenant_id' =>
                $workspace['tenant_id'],

            'cash_account_id' =>
                $account,

            'direction' =>
                'OUT',

            'amount' =>
                '100000.00',

            'currency' =>
                'IDR',

            'occurred_at' =>
                now(),

            'source_type' =>
                'MANUAL_INCOME_VOID',

            'source_id' =>
                $incomeId,

            'reference' =>
                null,

            'description' =>
                'Pembatalan pemasukan manual.',

            'reversal_of_transaction_id' =>
                $originalId,

            'created_by_user_id' =>
                $workspace['user_id'],

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        $this->assertDatabaseHas(
            'cash_transactions',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],

                'source_type' =>
                    'MANUAL_INCOME_VOID',

                'source_id' =>
                    $incomeId,

                'direction' =>
                    'OUT',

                'reversal_of_transaction_id' =>
                    $originalId,
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
                'Income Test Owner',

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

    private function cashAccount(
        array $workspace
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

            'name' =>
                'Kas Income Test',

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

    private function income(
        array $workspace,
        ?string $cashAccountId,
        string $amount = '100000.00',
        string $currency = 'IDR',
        string $status = 'DRAFT',
        ?string $createdBy = null,
        ?string $postedBy = null,
        ?string $voidedBy = null
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

            'cash_account_id' =>
                $cashAccountId,

            'amount' =>
                $amount,

            'currency' =>
                $currency,

            'occurred_at' =>
                now(),

            'category' =>
                'Lain-lain',

            'description' =>
                'Pemasukan test',

            'reference' =>
                null,

            'status' =>
                $status,

            'created_by_user_id' =>
                $createdBy
                ?? $workspace['user_id'],

            'posted_by_user_id' =>
                $postedBy,

            'posted_at' =>
                $postedBy !== null
                    ? now()
                    : null,

            'voided_by_user_id' =>
                $voidedBy,

            'voided_at' =>
                $voidedBy !== null
                    ? now()
                    : null,

            'void_reason' =>
                $voidedBy !== null
                    ? 'Void test'
                    : null,

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        return $id;
    }
}
