<?php

namespace Tests\Feature\Api;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use App\Models\CashAccount;
use App\Services\Payment\PublicPaymentAccountTokenService;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicInvoicePaymentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );

        config([
            'filesystems.private_disk' =>
                'local',
        ]);

        Storage::fake('local');
    }

    public function test_public_customer_can_submit_pending_payment_with_private_evidence(): void
    {
        $fixture =
            $this->fixture();

        $show =
            $this->getJson(
                '/api/public/v1/invoices/'
                . $fixture['public_token']
            );

        $show->assertOk();

        $accountToken =
            $show->json(
                'data.payment_options.bank_accounts.0.payment_account_token'
            );

        $this->assertIsString(
            $accountToken
        );

        $this->assertNotSame(
            $fixture['cash_account_id'],
            $accountToken
        );

        $response =
            $this->post(
                '/api/public/v1/invoices/'
                . $fixture['public_token']
                . '/payments',
                [
                    'payment_account_token' =>
                        $accountToken,

                    'amount' =>
                        '40000.00',

                    'paid_at' =>
                        now()->format(
                            'Y-m-d'
                        ),

                    'reference' =>
                        'TRX-CUSTOMER-001',

                    'evidence' =>
                        UploadedFile::fake()
                            ->create(
                                'bukti.jpg',
                                100,
                                'image/jpeg'
                            ),
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.status',
                'PENDING'
            )
            ->assertJsonPath(
                'data.amount',
                '40000.00'
            )
            ->assertJsonPath(
                'data.currency',
                'IDR'
            );

        $payment =
            DB::table('payments')
                ->where(
                    'intended_invoice_id',
                    $fixture['invoice_id']
                )
                ->first();

        $this->assertNotNull(
            $payment
        );

        $this->assertSame(
            $fixture['tenant_id'],
            $payment->tenant_id
        );

        $this->assertSame(
            $fixture['business_id'],
            $payment->business_id
        );

        $this->assertSame(
            $fixture['customer_id'],
            $payment->customer_id
        );

        $this->assertSame(
            $fixture['cash_account_id'],
            $payment->cash_account_id
        );

        $this->assertSame(
            'PENDING',
            $payment->status
        );

        $this->assertNull(
            $payment->created_by_user_id
        );

        $this->assertNotNull(
            $payment->evidence_file_id
        );

        $this->assertDatabaseMissing(
            'payment_allocations',
            [
                'payment_id' =>
                    $payment->id,
            ]
        );

        $this->assertSame(
            0,
            DB::table('cash_transactions')
                ->where(
                    'source_type',
                    'PAYMENT'
                )
                ->where(
                    'source_id',
                    $payment->id
                )
                ->count()
        );

        $this->assertDatabaseHas(
            'invoices',
            [
                'id' =>
                    $fixture['invoice_id'],

                'status' =>
                    'ISSUED',

                'paid_amount' =>
                    '0.00',

                'outstanding_amount' =>
                    '100000.00',
            ]
        );

        $file =
            DB::table('files')
                ->where(
                    'id',
                    $payment->evidence_file_id
                )
                ->first();

        $this->assertNotNull(
            $file
        );

        $this->assertSame(
            'PAYMENT_PROOF',
            $file->purpose
        );

        $this->assertSame(
            'PRIVATE',
            $file->visibility
        );

        $this->assertNull(
            $file->uploaded_by_user_id
        );

        Storage::disk(
            $file->storage_disk
        )->assertExists(
            $file->object_key
        );
    }

    public function test_public_payment_requires_evidence(): void
    {
        $fixture =
            $this->fixture();

        $show =
            $this->getJson(
                '/api/public/v1/invoices/'
                . $fixture['public_token']
            )->assertOk();

        $this->post(
            '/api/public/v1/invoices/'
            . $fixture['public_token']
            . '/payments',
            [
                'payment_account_token' =>
                    $show->json(
                        'data.payment_options.bank_accounts.0.payment_account_token'
                    ),

                'amount' =>
                    '100000.00',

                'paid_at' =>
                    now()->format(
                        'Y-m-d'
                    ),
            ]
        )->assertUnprocessable();

        $this->assertDatabaseMissing(
            'payments',
            [
                'intended_invoice_id' =>
                    $fixture['invoice_id'],
            ]
        );
    }

    public function test_public_payment_rejects_foreign_business_account_token(): void
    {
        $fixture =
            $this->fixture();

        $foreign =
            $this->workspace(
                'foreign-payment@example.test',
                'Foreign Payment'
            );

        $foreignAccountId =
            $this->cashAccount(
                $foreign
            );

        $foreignAccount =
            CashAccount::query()
                ->findOrFail(
                    $foreignAccountId
                );

        $foreignToken =
            app(
                PublicPaymentAccountTokenService::class
            )->issue(
                $foreignAccount
            );

        $this->post(
            '/api/public/v1/invoices/'
            . $fixture['public_token']
            . '/payments',
            [
                'payment_account_token' =>
                    $foreignToken,

                'amount' =>
                    '100000.00',

                'paid_at' =>
                    now()->format(
                        'Y-m-d'
                    ),

                'evidence' =>
                    UploadedFile::fake()
                        ->create(
                            'bukti.jpg',
                            100,
                            'image/jpeg'
                        ),
            ]
        )->assertUnprocessable();

        $this->assertDatabaseMissing(
            'payments',
            [
                'intended_invoice_id' =>
                    $fixture['invoice_id'],
            ]
        );
    }

    public function test_public_payment_respects_partial_payment_setting(): void
    {
        $fixture =
            $this->fixture(
                partialPaymentEnabled:
                    false
            );

        $show =
            $this->getJson(
                '/api/public/v1/invoices/'
                . $fixture['public_token']
            )->assertOk();

        $this->post(
            '/api/public/v1/invoices/'
            . $fixture['public_token']
            . '/payments',
            [
                'payment_account_token' =>
                    $show->json(
                        'data.payment_options.bank_accounts.0.payment_account_token'
                    ),

                'amount' =>
                    '40000.00',

                'paid_at' =>
                    now()->format(
                        'Y-m-d'
                    ),

                'evidence' =>
                    UploadedFile::fake()
                        ->create(
                            'bukti.jpg',
                            100,
                            'image/jpeg'
                        ),
            ]
        )->assertUnprocessable();

        $this->assertDatabaseMissing(
            'payments',
            [
                'intended_invoice_id' =>
                    $fixture['invoice_id'],
            ]
        );
    }

    private function fixture(
        bool $partialPaymentEnabled = true
    ): array {
        $workspace =
            $this->workspace(
                'public-payment-'
                . Str::lower(
                    (string) Str::ulid()
                )
                . '@example.test',
                'Public Payment'
            );

        $customerId =
            $this->customer(
                $workspace
            );

        $invoiceId =
            $this->invoice(
                $workspace,
                $customerId
            );

        $cashAccountId =
            $this->cashAccount(
                $workspace
            );

        $this->paymentSettings(
            $workspace,
            $partialPaymentEnabled
        );

        $publicToken =
            $this->publicInvoiceLink(
                $workspace,
                $invoiceId
            );

        return [
            ...$workspace,

            'customer_id' =>
                $customerId,

            'invoice_id' =>
                $invoiceId,

            'cash_account_id' =>
                $cashAccountId,

            'public_token' =>
                $publicToken,
        ];
    }

    private function workspace(
        string $email,
        string $tenantName
    ): array {
        $workspace =
            app(
                CreateTenantWorkspaceAction::class
            )->execute([
                'name' =>
                    'Owner',

                'email' =>
                    $email,

                'password' =>
                    'password',

                'tenant_name' =>
                    $tenantName,

                'timezone' =>
                    'Asia/Jakarta',
            ]);

        $businessId =
            DB::table('business_profiles')
                ->where(
                    'tenant_id',
                    $workspace['tenant_id']
                )
                ->where(
                    'is_default',
                    true
                )
                ->where(
                    'status',
                    'ACTIVE'
                )
                ->value('id');

        $this->assertNotNull(
            $businessId
        );

        return [
            'tenant_id' =>
                $workspace['tenant_id'],

            'business_id' =>
                (string) $businessId,

            'user_id' =>
                $workspace['user_id'],
        ];
    }

    private function customer(
        array $workspace
    ): string {
        $id =
            (string) Str::ulid();

        DB::table('customers')->insert([
            'id' =>
                $id,

            'tenant_id' =>
                $workspace['tenant_id'],

            'business_id' =>
                $workspace['business_id'],

            'type' =>
                'COMPANY',

            'code' =>
                'CUST-PUBLIC-PAY',

            'name' =>
                'Customer Public Payment',

            'status' =>
                'ACTIVE',

            'payment_terms_days' =>
                0,

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        return $id;
    }

    private function invoice(
        array $workspace,
        string $customerId
    ): string {
        $id =
            (string) Str::ulid();

        DB::table('invoices')->insert([
            'id' =>
                $id,

            'tenant_id' =>
                $workspace['tenant_id'],

            'business_id' =>
                $workspace['business_id'],

            'invoice_number' =>
                'INV-PUBLIC-PAY-001',

            'customer_id' =>
                $customerId,

            'status' =>
                'ISSUED',

            'issued_at' =>
                now(),

            'due_at' =>
                now()->addDays(7),

            'currency' =>
                'IDR',

            'subtotal' =>
                '100000.00',

            'discount_total' =>
                '0.00',

            'tax_total' =>
                '0.00',

            'total' =>
                '100000.00',

            'paid_amount' =>
                '0.00',

            'outstanding_amount' =>
                '100000.00',

            'created_by_user_id' =>
                $workspace['user_id'],

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        return $id;
    }

    private function cashAccount(
        array $workspace
    ): string {
        $id =
            (string) Str::ulid();

        DB::table('cash_accounts')->insert([
            'id' =>
                $id,

            'tenant_id' =>
                $workspace['tenant_id'],

            'business_id' =>
                $workspace['business_id'],

            'name' =>
                'Rekening Penerimaan',

            'type' =>
                'BANK',

            'bank_name' =>
                'BCA',

            'account_number' =>
                '1234567890',

            'account_name' =>
                'SIGNOVA TEST',

            'currency' =>
                'IDR',

            'status' =>
                'ACTIVE',

            'is_default' =>
                true,

            'accepts_payments' =>
                true,

            'created_by_user_id' =>
                $workspace['user_id'],

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        return $id;
    }

    private function paymentSettings(
        array $workspace,
        bool $partialPaymentEnabled
    ): void {
        $query =
            DB::table(
                'tenant_payment_settings'
            )
                ->where(
                    'tenant_id',
                    $workspace['tenant_id']
                )
                ->where(
                    'business_id',
                    $workspace['business_id']
                );

        if ($query->exists()) {
            $query->update([
                'bank_transfer_enabled' =>
                    true,

                'partial_payment_enabled' =>
                    $partialPaymentEnabled,

                'updated_at' =>
                    now(),
            ]);

            return;
        }

        DB::table(
            'tenant_payment_settings'
        )->insert([
            'id' =>
                (string) Str::ulid(),

            'tenant_id' =>
                $workspace['tenant_id'],

            'business_id' =>
                $workspace['business_id'],

            'bank_transfer_enabled' =>
                true,

            'static_qr_enabled' =>
                false,

            'midtrans_enabled' =>
                false,

            'partial_payment_enabled' =>
                $partialPaymentEnabled,

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);
    }

    private function publicInvoiceLink(
        array $workspace,
        string $invoiceId
    ): string {
        $rawToken =
            rtrim(
                strtr(
                    base64_encode(
                        random_bytes(32)
                    ),
                    '+/',
                    '-_'
                ),
                '='
            );

        DB::table(
            'invoice_public_links'
        )->insert([
            'id' =>
                (string) Str::ulid(),

            'tenant_id' =>
                $workspace['tenant_id'],

            'business_id' =>
                $workspace['business_id'],

            'invoice_id' =>
                $invoiceId,

            'token_hash' =>
                hash(
                    'sha256',
                    $rawToken
                ),

            'expires_at' =>
                null,

            'revoked_at' =>
                null,

            'created_by_user_id' =>
                $workspace['user_id'],

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        return 'TAGIHAN-'
            . $rawToken;
    }
}
