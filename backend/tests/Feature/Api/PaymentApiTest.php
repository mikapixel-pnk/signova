<?php

namespace Tests\Feature\Api;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );

        config()->set(
            'filesystems.private_disk',
            'local'
        );

        Storage::fake('local');
    }

    public function test_owner_can_record_manual_bank_transfer(): void
    {
        $workspace = $this->workspace(
            'payment-record@example.test',
            'Payment Record'
        );

        $customer = $this->customer(
            $workspace,
            'Customer A'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $response = $this->postJson(
            '/api/v1/payments',
            [
                'customer_id' =>
                    $customer->id,

                'amount' =>
                    250000,

                'currency' =>
                    'idr',

                'paid_at' =>
                    '2026-09-13 10:30:00',

                'method' =>
                    'bank_transfer',

                'reference' =>
                    'TRX-001',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.customer_id',
                $customer->id
            )
            ->assertJsonPath(
                'data.amount',
                '250000.00'
            )
            ->assertJsonPath(
                'data.currency',
                'IDR'
            )
            ->assertJsonPath(
                'data.method',
                'BANK_TRANSFER'
            )
            ->assertJsonPath(
                'data.status',
                'PENDING'
            )
            ->assertJsonPath(
                'data.reference',
                'TRX-001'
            )
            ->assertJsonPath(
                'data.has_evidence',
                false
            );

        $paymentId =
            $response->json(
                'data.id'
            );

        $this->assertDatabaseHas(
            'payments',
            [
                'id' =>
                    $paymentId,

                'tenant_id' =>
                    $workspace['tenant_id'],

                'customer_id' =>
                    $customer->id,

                'method' =>
                    'BANK_TRANSFER',

                'status' =>
                    'PENDING',

                'provider' =>
                    null,

                'provider_reference' =>
                    null,

                'provider_transaction_id' =>
                    null,

                'evidence_file_id' =>
                    null,
            ]
        );
    }

    public function test_owner_can_record_static_qr_payment(): void
    {
        $workspace = $this->workspace(
            'payment-qr@example.test',
            'Payment QR'
        );

        $customer = $this->customer(
            $workspace,
            'Customer QR'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->postJson(
            '/api/v1/payments',
            [
                'customer_id' =>
                    $customer->id,

                'amount' =>
                    175000,

                'paid_at' =>
                    now()->toISOString(),

                'method' =>
                    'STATIC_QR',
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.method',
                'STATIC_QR'
            )
            ->assertJsonPath(
                'data.status',
                'PENDING'
            );
    }

    public function test_manual_payment_rejects_midtrans_method(): void
    {
        $workspace = $this->workspace(
            'payment-midtrans-invalid@example.test',
            'Payment Midtrans Invalid'
        );

        $customer = $this->customer(
            $workspace,
            'Customer Midtrans'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->postJson(
            '/api/v1/payments',
            [
                'customer_id' =>
                    $customer->id,

                'amount' =>
                    100000,

                'paid_at' =>
                    now()->toISOString(),

                'method' =>
                    'MIDTRANS',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );

        $this->assertDatabaseCount(
            'payments',
            0
        );
    }

    public function test_manual_payment_requires_active_customer(): void
    {
        $workspace = $this->workspace(
            'payment-inactive@example.test',
            'Payment Inactive'
        );

        $customer = $this->customer(
            $workspace,
            'Inactive Customer',
            'INACTIVE'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->postJson(
            '/api/v1/payments',
            [
                'customer_id' =>
                    $customer->id,

                'amount' =>
                    100000,

                'paid_at' =>
                    now()->toISOString(),

                'method' =>
                    'BANK_TRANSFER',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );

        $this->assertDatabaseCount(
            'payments',
            0
        );
    }

    public function test_manual_payment_rejects_customer_from_other_tenant(): void
    {
        $first = $this->workspace(
            'payment-first@example.test',
            'Payment First'
        );

        $second = $this->workspace(
            'payment-second@example.test',
            'Payment Second'
        );

        $foreignCustomer =
            $this->customer(
                $second,
                'Foreign Customer'
            );

        $this->actingAsWorkspace(
            $first
        );

        $this->postJson(
            '/api/v1/payments',
            [
                'customer_id' =>
                    $foreignCustomer->id,

                'amount' =>
                    100000,

                'paid_at' =>
                    now()->toISOString(),

                'method' =>
                    'BANK_TRANSFER',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );

        $this->assertDatabaseCount(
            'payments',
            0
        );
    }

    public function test_payment_list_is_tenant_scoped_and_filterable(): void
    {
        $first = $this->workspace(
            'payment-list-first@example.test',
            'Payment List First'
        );

        $second = $this->workspace(
            'payment-list-second@example.test',
            'Payment List Second'
        );

        $firstCustomer =
            $this->customer(
                $first,
                'Alpha Customer'
            );

        $secondCustomer =
            $this->customer(
                $second,
                'Foreign Customer'
            );

        $this->actingAsWorkspace(
            $first
        );

        $this->postJson(
            '/api/v1/payments',
            [
                'customer_id' =>
                    $firstCustomer->id,
                'amount' => 125000,
                'paid_at' =>
                    now()->toISOString(),
                'method' =>
                    'BANK_TRANSFER',
                'reference' =>
                    'ALPHA-REF',
            ]
        )->assertCreated();

        $this->actingAsWorkspace(
            $second
        );

        $this->postJson(
            '/api/v1/payments',
            [
                'customer_id' =>
                    $secondCustomer->id,
                'amount' => 999000,
                'paid_at' =>
                    now()->toISOString(),
                'method' =>
                    'STATIC_QR',
                'reference' =>
                    'FOREIGN-REF',
            ]
        )->assertCreated();

        $this->actingAsWorkspace(
            $first
        );

        $this->getJson(
            '/api/v1/payments'
            . '?search=Alpha'
            . '&status=PENDING'
            . '&method=bank_transfer'
            . '&customer_id='
            . $firstCustomer->id
        )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.reference',
                'ALPHA-REF'
            )
            ->assertJsonPath(
                'meta.total',
                1
            );
    }

    public function test_foreign_tenant_payment_detail_returns_not_found(): void
    {
        $first = $this->workspace(
            'payment-detail-first@example.test',
            'Payment Detail First'
        );

        $second = $this->workspace(
            'payment-detail-second@example.test',
            'Payment Detail Second'
        );

        $customer =
            $this->customer(
                $second,
                'Detail Customer'
            );

        $this->actingAsWorkspace(
            $second
        );

        $response =
            $this->postJson(
                '/api/v1/payments',
                [
                    'customer_id' =>
                        $customer->id,
                    'amount' =>
                        100000,
                    'paid_at' =>
                        now()->toISOString(),
                    'method' =>
                        'BANK_TRANSFER',
                ]
            )->assertCreated();

        $paymentId =
            $response->json(
                'data.id'
            );

        $this->actingAsWorkspace(
            $first
        );

        $this->getJson(
            '/api/v1/payments/'
            . $paymentId
        )->assertNotFound();
    }

    public function test_owner_can_upload_private_payment_evidence(): void
    {
        $workspace = $this->workspace(
            'payment-evidence@example.test',
            'Payment Evidence'
        );

        $customer = $this->customer(
            $workspace,
            'Evidence Customer'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $response =
            $this->postJson(
                '/api/v1/payments',
                [
                    'customer_id' =>
                        $customer->id,
                    'amount' =>
                        300000,
                    'paid_at' =>
                        now()->toISOString(),
                    'method' =>
                        'BANK_TRANSFER',
                ]
            )->assertCreated();

        $paymentId =
            $response->json(
                'data.id'
            );

        $this->post(
            '/api/v1/payments/'
            . $paymentId
            . '/evidence',
            [
                'evidence' =>
                    UploadedFile::fake()
                        ->image(
                            'proof.png',
                            800,
                            800
                        ),
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.has_evidence',
                true
            )
            ->assertJsonPath(
                'data.evidence.mime_type',
                'image/png'
            );

        $file =
            DB::table('files')
                ->where(
                    'tenant_id',
                    $workspace['tenant_id']
                )
                ->where(
                    'purpose',
                    'PAYMENT_PROOF'
                )
                ->first();

        $this->assertNotNull(
            $file
        );

        $this->assertSame(
            'PRIVATE',
            $file->visibility
        );

        $this->assertStringStartsWith(
            'tenants/'
                . $workspace['tenant_id']
                . '/payment-proofs/',
            $file->object_key
        );

        Storage::disk('local')
            ->assertExists(
                $file->object_key
            );

        $this->assertDatabaseHas(
            'payments',
            [
                'id' =>
                    $paymentId,

                'evidence_file_id' =>
                    $file->id,

                'status' =>
                    'PENDING',
            ]
        );
    }

    public function test_new_evidence_replaces_old_evidence(): void
    {
        $workspace = $this->workspace(
            'payment-replace@example.test',
            'Payment Replace'
        );

        $customer = $this->customer(
            $workspace,
            'Replace Customer'
        );

        $paymentId =
            $this->createPayment(
                $workspace,
                $customer
            );

        $this->post(
            '/api/v1/payments/'
            . $paymentId
            . '/evidence',
            [
                'evidence' =>
                    UploadedFile::fake()
                        ->image('old.png'),
            ]
        )->assertOk();

        $old =
            DB::table('files')
                ->where(
                    'tenant_id',
                    $workspace['tenant_id']
                )
                ->where(
                    'purpose',
                    'PAYMENT_PROOF'
                )
                ->first();

        $this->assertNotNull(
            $old
        );

        $this->post(
            '/api/v1/payments/'
            . $paymentId
            . '/evidence',
            [
                'evidence' =>
                    UploadedFile::fake()
                        ->image('new.png'),
            ]
        )->assertOk();

        $new =
            DB::table('files')
                ->where(
                    'tenant_id',
                    $workspace['tenant_id']
                )
                ->where(
                    'purpose',
                    'PAYMENT_PROOF'
                )
                ->first();

        $this->assertNotNull(
            $new
        );

        $this->assertNotSame(
            $old->id,
            $new->id
        );

        $this->assertDatabaseMissing(
            'files',
            [
                'id' => $old->id,
            ]
        );

        Storage::disk('local')
            ->assertMissing(
                $old->object_key
            );

        Storage::disk('local')
            ->assertExists(
                $new->object_key
            );
    }

    public function test_owner_can_preview_and_remove_payment_evidence(): void
    {
        $workspace = $this->workspace(
            'payment-preview@example.test',
            'Payment Preview'
        );

        $customer = $this->customer(
            $workspace,
            'Preview Customer'
        );

        $paymentId =
            $this->createPayment(
                $workspace,
                $customer
            );

        $this->post(
            '/api/v1/payments/'
            . $paymentId
            . '/evidence',
            [
                'evidence' =>
                    UploadedFile::fake()
                        ->image('preview.png'),
            ]
        )->assertOk();

        $file =
            DB::table('files')
                ->where(
                    'tenant_id',
                    $workspace['tenant_id']
                )
                ->where(
                    'purpose',
                    'PAYMENT_PROOF'
                )
                ->first();

        $this->get(
            '/api/v1/payments/'
            . $paymentId
            . '/evidence'
        )
            ->assertOk()
            ->assertHeader(
                'Content-Type',
                'image/png'
            )
            ->assertHeader(
                'X-Content-Type-Options',
                'nosniff'
            );

        $this->deleteJson(
            '/api/v1/payments/'
            . $paymentId
            . '/evidence'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.has_evidence',
                false
            );

        $this->assertDatabaseMissing(
            'files',
            [
                'id' => $file->id,
            ]
        );

        Storage::disk('local')
            ->assertMissing(
                $file->object_key
            );
    }

    public function test_tenant_cannot_access_foreign_payment_evidence(): void
    {
        $first = $this->workspace(
            'payment-proof-first@example.test',
            'Payment Proof First'
        );

        $second = $this->workspace(
            'payment-proof-second@example.test',
            'Payment Proof Second'
        );

        $customer =
            $this->customer(
                $second,
                'Proof Customer'
            );

        $paymentId =
            $this->createPayment(
                $second,
                $customer
            );

        $this->post(
            '/api/v1/payments/'
            . $paymentId
            . '/evidence',
            [
                'evidence' =>
                    UploadedFile::fake()
                        ->image('foreign.png'),
            ]
        )->assertOk();

        $this->actingAsWorkspace(
            $first
        );

        $this->get(
            '/api/v1/payments/'
            . $paymentId
            . '/evidence'
        )->assertNotFound();

        $this->deleteJson(
            '/api/v1/payments/'
            . $paymentId
            . '/evidence'
        )->assertNotFound();
    }

    public function test_payment_capabilities_are_enforced(): void
    {
        $workspace = $this->workspace(
            'payment-capability@example.test',
            'Payment Capability'
        );

        $customer = $this->customer(
            $workspace,
            'Capability Customer'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->denyCapability(
            $workspace,
            'payment.record'
        );

        $this->postJson(
            '/api/v1/payments',
            [
                'customer_id' =>
                    $customer->id,
                'amount' =>
                    100000,
                'paid_at' =>
                    now()->toISOString(),
                'method' =>
                    'BANK_TRANSFER',
            ]
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );

        $this->denyCapability(
            $workspace,
            'payment.view'
        );

        $this->getJson(
            '/api/v1/payments'
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );
    }

    private function createPayment(
        array $workspace,
        Customer $customer
    ): string {
        $this->actingAsWorkspace(
            $workspace
        );

        $response =
            $this->postJson(
                '/api/v1/payments',
                [
                    'customer_id' =>
                        $customer->id,

                    'amount' =>
                        100000,

                    'paid_at' =>
                        now()->toISOString(),

                    'method' =>
                        'BANK_TRANSFER',
                ]
            );

        $response->assertCreated();

        return (string) $response->json(
            'data.id'
        );
    }

    private function customer(
        array $workspace,
        string $name,
        string $status = 'ACTIVE'
    ): Customer {
        return Customer::query()
            ->create([
                'tenant_id' =>
                    $workspace['tenant_id'],

                'type' =>
                    'COMPANY',

                'name' =>
                    $name,

                'payment_terms_days' =>
                    0,

                'status' =>
                    $status,
            ]);
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
            DB::table('roles')
                ->where(
                    'tenant_id',
                    $workspace['tenant_id']
                )
                ->where(
                    'code',
                    'OWNER'
                )
                ->value('id');

        $capabilityId =
            DB::table('capabilities')
                ->where(
                    'code',
                    $capability
                )
                ->value('id');

        $this->assertNotNull(
            $roleId
        );

        $this->assertNotNull(
            $capabilityId
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
