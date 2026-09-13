<?php

namespace Tests\Feature\Tenancy;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentFoundationIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_payment_customer_must_belong_to_same_tenant(): void
    {
        $first = $this->workspace(
            'payment-a@example.test',
            'Payment A'
        );

        $second = $this->workspace(
            'payment-b@example.test',
            'Payment B'
        );

        $foreignCustomer = $this->customer(
            $second['tenant_id'],
            'CUST-B'
        );

        $this->expectException(
            QueryException::class
        );

        $this->payment(
            $first,
            $foreignCustomer
        );
    }

    public function test_payment_evidence_file_must_belong_to_same_tenant(): void
    {
        $first = $this->workspace(
            'evidence-a@example.test',
            'Evidence A'
        );

        $second = $this->workspace(
            'evidence-b@example.test',
            'Evidence B'
        );

        $customer = $this->customer(
            $first['tenant_id'],
            'CUST-A'
        );

        $foreignFile = $this->file(
            $second
        );

        $this->expectException(
            QueryException::class
        );

        $this->payment(
            $first,
            $customer,
            $foreignFile
        );
    }

    public function test_allocation_cannot_cross_tenants(): void
    {
        $first = $this->workspace(
            'allocation-a@example.test',
            'Allocation A'
        );

        $second = $this->workspace(
            'allocation-b@example.test',
            'Allocation B'
        );

        $customerA = $this->customer(
            $first['tenant_id'],
            'CUST-A'
        );

        $customerB = $this->customer(
            $second['tenant_id'],
            'CUST-B'
        );

        $paymentId = $this->payment(
            $first,
            $customerA
        );

        $foreignInvoice = $this->invoice(
            $second,
            $customerB,
            'INV-B-001'
        );

        $this->expectException(
            QueryException::class
        );

        DB::table(
            'payment_allocations'
        )->insert([
            'id' => (string) Str::ulid(),
            'tenant_id' =>
                $first['tenant_id'],
            'payment_id' =>
                $paymentId,
            'invoice_id' =>
                $foreignInvoice,
            'allocated_amount' =>
                '10000.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_static_qr_file_must_belong_to_same_tenant(): void
    {
        $first = $this->workspace(
            'qr-a@example.test',
            'QR A'
        );

        $second = $this->workspace(
            'qr-b@example.test',
            'QR B'
        );

        $foreignFile = $this->file(
            $second
        );

        $this->expectException(
            QueryException::class
        );

        DB::table(
            'tenant_payment_settings'
        )->insert([
            'tenant_id' =>
                $first['tenant_id'],
            'static_qr_enabled' =>
                true,
            'static_qr_file_id' =>
                $foreignFile,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_payment_amount_must_be_positive(): void
    {
        $workspace = $this->workspace(
            'amount@example.test',
            'Amount'
        );

        $customer = $this->customer(
            $workspace['tenant_id'],
            'CUST-AMOUNT'
        );

        $this->expectException(
            QueryException::class
        );

        $this->payment(
            $workspace,
            $customer,
            null,
            '0.00'
        );
    }

    public function test_allocation_amount_must_be_positive(): void
    {
        $workspace = $this->workspace(
            'allocation-positive@example.test',
            'Allocation Positive'
        );

        $customer = $this->customer(
            $workspace['tenant_id'],
            'CUST-ALLOC'
        );

        $paymentId = $this->payment(
            $workspace,
            $customer
        );

        $invoiceId = $this->invoice(
            $workspace,
            $customer,
            'INV-ALLOC-001'
        );

        $this->expectException(
            QueryException::class
        );

        DB::table(
            'payment_allocations'
        )->insert([
            'id' => (string) Str::ulid(),
            'tenant_id' =>
                $workspace['tenant_id'],
            'payment_id' =>
                $paymentId,
            'invoice_id' =>
                $invoiceId,
            'allocated_amount' =>
                '0.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_provider_environment_must_be_canonical(): void
    {
        $workspace = $this->workspace(
            'provider-env@example.test',
            'Provider Env'
        );

        $this->expectException(
            QueryException::class
        );

        DB::table(
            'payment_provider_accounts'
        )->insert([
            'id' => (string) Str::ulid(),
            'tenant_id' =>
                $workspace['tenant_id'],
            'provider' => 'MIDTRANS',
            'environment' => 'INVALID',
            'connection_status' =>
                'DISCONNECTED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_one_provider_account_per_tenant(): void
    {
        $workspace = $this->workspace(
            'provider-unique@example.test',
            'Provider Unique'
        );

        $this->providerAccount(
            $workspace,
            'MIDTRANS'
        );

        $this->expectException(
            QueryException::class
        );

        $this->providerAccount(
            $workspace,
            'MIDTRANS'
        );
    }

    public function test_provider_transaction_id_is_unique_inside_tenant(): void
    {
        $workspace = $this->workspace(
            'provider-transaction@example.test',
            'Provider Transaction'
        );

        $customer = $this->customer(
            $workspace['tenant_id'],
            'CUST-PROVIDER'
        );

        $this->payment(
            $workspace,
            $customer,
            null,
            '10000.00',
            'MIDTRANS',
            'MIDTRANS',
            'trx-001'
        );

        $this->expectException(
            QueryException::class
        );

        $this->payment(
            $workspace,
            $customer,
            null,
            '12000.00',
            'MIDTRANS',
            'MIDTRANS',
            'trx-001'
        );
    }

    public function test_same_provider_transaction_id_is_allowed_across_tenants(): void
    {
        $first = $this->workspace(
            'provider-tx-a@example.test',
            'Provider TX A'
        );

        $second = $this->workspace(
            'provider-tx-b@example.test',
            'Provider TX B'
        );

        $customerA = $this->customer(
            $first['tenant_id'],
            'CUST-A'
        );

        $customerB = $this->customer(
            $second['tenant_id'],
            'CUST-B'
        );

        $this->payment(
            $first,
            $customerA,
            null,
            '10000.00',
            'MIDTRANS',
            'MIDTRANS',
            'trx-shared'
        );

        $this->payment(
            $second,
            $customerB,
            null,
            '10000.00',
            'MIDTRANS',
            'MIDTRANS',
            'trx-shared'
        );

        $this->assertDatabaseCount(
            'payments',
            2
        );
    }


    public function test_provider_credentials_are_encrypted_at_rest(): void
    {
        $workspace = $this->workspace(
            'provider-secret@example.test',
            'Provider Secret'
        );

        $account = new \App\Models\PaymentProviderAccount();

        $account->tenant_id =
            $workspace['tenant_id'];

        $account->provider =
            'MIDTRANS';

        $account->environment =
            'SANDBOX';

        $account->connection_status =
            'DISCONNECTED';

        $account->client_key_encrypted =
            'client-key-test';

        $account->server_key_encrypted =
            'server-key-test';

        $account->save();

        $stored = DB::table(
            'payment_provider_accounts'
        )
            ->where(
                'id',
                $account->id
            )
            ->first();

        $this->assertNotNull(
            $stored
        );

        $this->assertNotSame(
            'client-key-test',
            $stored->client_key_encrypted
        );

        $this->assertNotSame(
            'server-key-test',
            $stored->server_key_encrypted
        );

        $fresh = \App\Models\PaymentProviderAccount::query()
            ->findOrFail(
                $account->id
            );

        $this->assertSame(
            'client-key-test',
            $fresh->client_key_encrypted
        );

        $this->assertSame(
            'server-key-test',
            $fresh->server_key_encrypted
        );

        $serialized = $fresh->toArray();

        $this->assertArrayNotHasKey(
            'client_key_encrypted',
            $serialized
        );

        $this->assertArrayNotHasKey(
            'server_key_encrypted',
            $serialized
        );
    }

    public function test_reversal_actor_must_belong_to_same_tenant(): void
    {
        $first = $this->workspace(
            'reversal-a@example.test',
            'Reversal A'
        );

        $second = $this->workspace(
            'reversal-b@example.test',
            'Reversal B'
        );

        $customer = $this->customer(
            $first['tenant_id'],
            'CUST-REV'
        );

        $paymentId = $this->payment(
            $first,
            $customer
        );

        $this->expectException(
            QueryException::class
        );

        DB::table(
            'payment_reversals'
        )->insert([
            'id' => (string) Str::ulid(),
            'tenant_id' =>
                $first['tenant_id'],
            'payment_id' =>
                $paymentId,
            'amount' => '10000.00',
            'reason' =>
                'Test cross tenant actor',
            'reversed_by_user_id' =>
                $second['user_id'],
            'reversed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function workspace(
        string $email,
        string $tenantName
    ): array {
        $workspace = app(
            CreateTenantWorkspaceAction::class
        )->execute([
            'name' => 'Owner',
            'email' => $email,
            'password' => 'password',
            'tenant_name' => $tenantName,
            'timezone' => 'Asia/Jakarta',
        ]);

        return [
            'tenant_id' =>
                $workspace['tenant_id'],
            'user_id' =>
                $workspace['user_id'],
        ];
    }

    private function customer(
        string $tenantId,
        string $code
    ): string {
        $id = (string) Str::ulid();

        DB::table('customers')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'type' => 'COMPANY',
            'code' => $code,
            'name' => $code,
            'status' => 'ACTIVE',
            'payment_terms_days' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function invoice(
        array $workspace,
        string $customerId,
        string $number
    ): string {
        $id = (string) Str::ulid();

        DB::table('invoices')->insert([
            'id' => $id,
            'tenant_id' =>
                $workspace['tenant_id'],
            'invoice_number' => $number,
            'customer_id' => $customerId,
            'status' => 'ISSUED',
            'issued_at' => now(),
            'currency' => 'IDR',
            'subtotal' => '100000.00',
            'discount_total' => '0.00',
            'tax_total' => '0.00',
            'total' => '100000.00',
            'created_by_user_id' =>
                $workspace['user_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function file(
        array $workspace
    ): string {
        $id = (string) Str::ulid();

        DB::table('files')->insert([
            'id' => $id,
            'tenant_id' =>
                $workspace['tenant_id'],
            'purpose' => 'PAYMENT_PROOF',
            'storage_disk' => 'local',
            'object_key' =>
                'tenants/'
                . $workspace['tenant_id']
                . '/test/'
                . $id
                . '.png',
            'original_name' => 'test.png',
            'mime_type' => 'image/png',
            'size_bytes' => 100,
            'visibility' => 'PRIVATE',
            'uploaded_by_user_id' =>
                $workspace['user_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function payment(
        array $workspace,
        string $customerId,
        ?string $evidenceFileId = null,
        string $amount = '10000.00',
        string $method = 'BANK_TRANSFER',
        ?string $provider = null,
        ?string $providerTransactionId = null
    ): string {
        $id = (string) Str::ulid();

        DB::table('payments')->insert([
            'id' => $id,
            'tenant_id' =>
                $workspace['tenant_id'],
            'customer_id' => $customerId,
            'amount' => $amount,
            'currency' => 'IDR',
            'paid_at' => now(),
            'method' => $method,
            'status' => 'PENDING',
            'evidence_file_id' =>
                $evidenceFileId,
            'provider' => $provider,
            'provider_transaction_id' =>
                $providerTransactionId,
            'created_by_user_id' =>
                $workspace['user_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function providerAccount(
        array $workspace,
        string $provider
    ): string {
        $id = (string) Str::ulid();

        DB::table(
            'payment_provider_accounts'
        )->insert([
            'id' => $id,
            'tenant_id' =>
                $workspace['tenant_id'],
            'provider' => $provider,
            'environment' => 'SANDBOX',
            'connection_status' =>
                'DISCONNECTED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
