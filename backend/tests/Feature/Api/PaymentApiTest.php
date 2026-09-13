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

    public function test_pending_payment_can_be_verified(): void
    {
        $workspace = $this->workspace(
            'payment-verify@example.test',
            'Payment Verify'
        );

        $customer = $this->customer(
            $workspace,
            'Verify Customer'
        );

        $paymentId =
            $this->createPayment(
                $workspace,
                $customer
            );

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/actions/verify'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'VERIFIED'
            )
            ->assertJsonPath(
                'data.rejected_at',
                null
            )
            ->assertJsonPath(
                'data.rejection_reason',
                null
            );

        $payment =
            DB::table('payments')
                ->where(
                    'id',
                    $paymentId
                )
                ->first();

        $this->assertNotNull(
            $payment
        );

        $this->assertSame(
            'VERIFIED',
            $payment->status
        );

        $this->assertSame(
            $workspace['user_id'],
            $payment->verified_by_user_id
        );

        $this->assertNotNull(
            $payment->verified_at
        );

        $this->assertNull(
            $payment->rejected_by_user_id
        );

        $this->assertNull(
            $payment->rejected_at
        );

        $this->assertNull(
            $payment->rejection_reason
        );
    }

    public function test_pending_payment_can_be_rejected_with_reason(): void
    {
        $workspace = $this->workspace(
            'payment-reject@example.test',
            'Payment Reject'
        );

        $customer = $this->customer(
            $workspace,
            'Reject Customer'
        );

        $paymentId =
            $this->createPayment(
                $workspace,
                $customer
            );

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/actions/reject',
            [
                'reason' =>
                    'Bukti transfer tidak valid.',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'REJECTED'
            )
            ->assertJsonPath(
                'data.rejection_reason',
                'Bukti transfer tidak valid.'
            )
            ->assertJsonPath(
                'data.verified_at',
                null
            );

        $payment =
            DB::table('payments')
                ->where(
                    'id',
                    $paymentId
                )
                ->first();

        $this->assertSame(
            'REJECTED',
            $payment->status
        );

        $this->assertSame(
            $workspace['user_id'],
            $payment->rejected_by_user_id
        );

        $this->assertNotNull(
            $payment->rejected_at
        );

        $this->assertSame(
            'Bukti transfer tidak valid.',
            $payment->rejection_reason
        );

        $this->assertNull(
            $payment->verified_by_user_id
        );

        $this->assertNull(
            $payment->verified_at
        );
    }

    public function test_reject_requires_reason(): void
    {
        $workspace = $this->workspace(
            'payment-reject-reason@example.test',
            'Payment Reject Reason'
        );

        $customer = $this->customer(
            $workspace,
            'Reason Customer'
        );

        $paymentId =
            $this->createPayment(
                $workspace,
                $customer
            );

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/actions/reject',
            [
                'reason' => '   ',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );

        $this->assertDatabaseHas(
            'payments',
            [
                'id' => $paymentId,
                'status' => 'PENDING',
            ]
        );
    }

    public function test_verified_payment_cannot_be_verified_again(): void
    {
        $workspace = $this->workspace(
            'payment-double-verify@example.test',
            'Payment Double Verify'
        );

        $customer = $this->customer(
            $workspace,
            'Double Verify Customer'
        );

        $paymentId =
            $this->createPayment(
                $workspace,
                $customer
            );

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/actions/verify'
        )->assertOk();

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/actions/verify'
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'INVALID_TRANSITION'
            );
    }

    public function test_verified_payment_cannot_be_rejected(): void
    {
        $workspace = $this->workspace(
            'payment-verify-reject@example.test',
            'Payment Verify Reject'
        );

        $customer = $this->customer(
            $workspace,
            'Verify Reject Customer'
        );

        $paymentId =
            $this->createPayment(
                $workspace,
                $customer
            );

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/actions/verify'
        )->assertOk();

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/actions/reject',
            [
                'reason' =>
                    'Tidak boleh.',
            ]
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'INVALID_TRANSITION'
            );
    }

    public function test_rejected_payment_cannot_be_verified(): void
    {
        $workspace = $this->workspace(
            'payment-reject-verify@example.test',
            'Payment Reject Verify'
        );

        $customer = $this->customer(
            $workspace,
            'Reject Verify Customer'
        );

        $paymentId =
            $this->createPayment(
                $workspace,
                $customer
            );

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/actions/reject',
            [
                'reason' =>
                    'Ditolak.',
            ]
        )->assertOk();

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/actions/verify'
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'INVALID_TRANSITION'
            );
    }

    public function test_rejected_payment_cannot_be_rejected_again(): void
    {
        $workspace = $this->workspace(
            'payment-double-reject@example.test',
            'Payment Double Reject'
        );

        $customer = $this->customer(
            $workspace,
            'Double Reject Customer'
        );

        $paymentId =
            $this->createPayment(
                $workspace,
                $customer
            );

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/actions/reject',
            [
                'reason' =>
                    'Pertama.',
            ]
        )->assertOk();

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/actions/reject',
            [
                'reason' =>
                    'Kedua.',
            ]
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'INVALID_TRANSITION'
            );
    }

    public function test_foreign_tenant_cannot_verify_or_reject_payment(): void
    {
        $first = $this->workspace(
            'payment-action-first@example.test',
            'Payment Action First'
        );

        $second = $this->workspace(
            'payment-action-second@example.test',
            'Payment Action Second'
        );

        $customer = $this->customer(
            $second,
            'Action Customer'
        );

        $paymentId =
            $this->createPayment(
                $second,
                $customer
            );

        $this->actingAsWorkspace(
            $first
        );

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/actions/verify'
        )->assertNotFound();

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/actions/reject',
            [
                'reason' =>
                    'Foreign reject.',
            ]
        )->assertNotFound();
    }

    public function test_verify_capability_is_enforced(): void
    {
        $workspace = $this->workspace(
            'payment-verify-capability@example.test',
            'Payment Verify Capability'
        );

        $customer = $this->customer(
            $workspace,
            'Verify Capability Customer'
        );

        $paymentId =
            $this->createPayment(
                $workspace,
                $customer
            );

        $this->denyCapability(
            $workspace,
            'payment.verify'
        );

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/actions/verify'
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/actions/reject',
            [
                'reason' =>
                    'Tidak punya akses.',
            ]
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );

        $this->assertDatabaseHas(
            'payments',
            [
                'id' => $paymentId,
                'status' => 'PENDING',
            ]
        );
    }

    public function test_verified_payment_can_partially_pay_issued_invoice(): void
    {
        $workspace = $this->workspace(
            'allocation-partial@example.test',
            'Allocation Partial'
        );

        $customer = $this->customer(
            $workspace,
            'Allocation Customer'
        );

        $invoiceId =
            $this->createReceivableInvoice(
                $workspace,
                $customer,
                'INV-ALLOC-PARTIAL',
                '1000000.00',
                'ISSUED'
            );

        $paymentId =
            $this->createPaymentWithAmount(
                $workspace,
                $customer,
                '400000.00'
            );

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/actions/verify'
        )->assertOk();

        $response =
            $this->postJson(
                '/api/v1/payments/'
                . $paymentId
                . '/allocations',
                [
                    'invoice_id' =>
                        $invoiceId,

                    'amount' =>
                        '400000.00',
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.invoice.status',
                'PARTIALLY_PAID'
            )
            ->assertJsonPath(
                'data.invoice.paid_amount',
                '400000.00'
            )
            ->assertJsonPath(
                'data.invoice.outstanding_amount',
                '600000.00'
            )
            ->assertJsonPath(
                'data.payment_allocated_amount',
                '400000.00'
            )
            ->assertJsonPath(
                'data.payment_unallocated_amount',
                '0.00'
            );

        $this->assertDatabaseHas(
            'payment_allocations',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],

                'payment_id' =>
                    $paymentId,

                'invoice_id' =>
                    $invoiceId,

                'allocated_amount' =>
                    '400000.00',
            ]
        );

        $this->assertDatabaseHas(
            'invoices',
            [
                'id' =>
                    $invoiceId,

                'status' =>
                    'PARTIALLY_PAID',

                'paid_amount' =>
                    '400000.00',

                'outstanding_amount' =>
                    '600000.00',
            ]
        );

        $history =
            DB::table(
                'invoice_status_history'
            )
                ->where(
                    'tenant_id',
                    $workspace['tenant_id']
                )
                ->where(
                    'invoice_id',
                    $invoiceId
                )
                ->where(
                    'source',
                    'PAYMENT'
                )
                ->latest(
                    'occurred_at'
                )
                ->first();

        $this->assertNotNull(
            $history
        );

        $this->assertSame(
            'ISSUED',
            $history->from_state
        );

        $this->assertSame(
            'PARTIALLY_PAID',
            $history->to_state
        );
    }

    public function test_verified_payments_can_complete_invoice_to_paid(): void
    {
        $workspace = $this->workspace(
            'allocation-paid@example.test',
            'Allocation Paid'
        );

        $customer = $this->customer(
            $workspace,
            'Paid Customer'
        );

        $invoiceId =
            $this->createReceivableInvoice(
                $workspace,
                $customer,
                'INV-ALLOC-PAID',
                '1000000.00',
                'ISSUED'
            );

        $firstPayment =
            $this->createPaymentWithAmount(
                $workspace,
                $customer,
                '400000.00'
            );

        $this->postJson(
            '/api/v1/payments/'
            . $firstPayment
            . '/actions/verify'
        )->assertOk();

        $this->postJson(
            '/api/v1/payments/'
            . $firstPayment
            . '/allocations',
            [
                'invoice_id' =>
                    $invoiceId,

                'amount' =>
                    '400000.00',
            ]
        )->assertCreated();

        $secondPayment =
            $this->createPaymentWithAmount(
                $workspace,
                $customer,
                '600000.00'
            );

        $this->postJson(
            '/api/v1/payments/'
            . $secondPayment
            . '/actions/verify'
        )->assertOk();

        $this->postJson(
            '/api/v1/payments/'
            . $secondPayment
            . '/allocations',
            [
                'invoice_id' =>
                    $invoiceId,

                'amount' =>
                    '600000.00',
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.invoice.status',
                'PAID'
            )
            ->assertJsonPath(
                'data.invoice.paid_amount',
                '1000000.00'
            )
            ->assertJsonPath(
                'data.invoice.outstanding_amount',
                '0.00'
            );

        $this->assertDatabaseHas(
            'invoices',
            [
                'id' =>
                    $invoiceId,

                'status' =>
                    'PAID',

                'paid_amount' =>
                    '1000000.00',

                'outstanding_amount' =>
                    '0.00',
            ]
        );

        $this->assertSame(
            2,
            DB::table(
                'invoice_status_history'
            )
                ->where(
                    'invoice_id',
                    $invoiceId
                )
                ->where(
                    'source',
                    'PAYMENT'
                )
                ->count()
        );
    }

    public function test_allocation_cannot_exceed_payment_amount(): void
    {
        $workspace = $this->workspace(
            'allocation-payment-limit@example.test',
            'Allocation Payment Limit'
        );

        $customer = $this->customer(
            $workspace,
            'Payment Limit Customer'
        );

        $invoiceId =
            $this->createReceivableInvoice(
                $workspace,
                $customer,
                'INV-PAYMENT-LIMIT',
                '500000.00',
                'ISSUED'
            );

        $paymentId =
            $this->createPaymentWithAmount(
                $workspace,
                $customer,
                '100000.00'
            );

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/actions/verify'
        )->assertOk();

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/allocations',
            [
                'invoice_id' =>
                    $invoiceId,

                'amount' =>
                    '150000.00',
            ]
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'PAYMENT_ALLOCATION_CONFLICT'
            );

        $this->assertDatabaseMissing(
            'payment_allocations',
            [
                'payment_id' =>
                    $paymentId,

                'invoice_id' =>
                    $invoiceId,
            ]
        );
    }

    public function test_allocation_cannot_exceed_invoice_outstanding(): void
    {
        $workspace = $this->workspace(
            'allocation-invoice-limit@example.test',
            'Allocation Invoice Limit'
        );

        $customer = $this->customer(
            $workspace,
            'Invoice Limit Customer'
        );

        $invoiceId =
            $this->createReceivableInvoice(
                $workspace,
                $customer,
                'INV-INVOICE-LIMIT',
                '100000.00',
                'ISSUED'
            );

        $paymentId =
            $this->createPaymentWithAmount(
                $workspace,
                $customer,
                '200000.00'
            );

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/actions/verify'
        )->assertOk();

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/allocations',
            [
                'invoice_id' =>
                    $invoiceId,

                'amount' =>
                    '150000.00',
            ]
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'PAYMENT_ALLOCATION_CONFLICT'
            );
    }

    public function test_payment_and_invoice_customer_must_match(): void
    {
        $workspace = $this->workspace(
            'allocation-customer@example.test',
            'Allocation Customer Match'
        );

        $paymentCustomer =
            $this->customer(
                $workspace,
                'Payment Customer'
            );

        $invoiceCustomer =
            $this->customer(
                $workspace,
                'Invoice Customer'
            );

        $invoiceId =
            $this->createReceivableInvoice(
                $workspace,
                $invoiceCustomer,
                'INV-CUSTOMER-MISMATCH',
                '100000.00',
                'ISSUED'
            );

        $paymentId =
            $this->createPaymentWithAmount(
                $workspace,
                $paymentCustomer,
                '100000.00'
            );

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/actions/verify'
        )->assertOk();

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/allocations',
            [
                'invoice_id' =>
                    $invoiceId,

                'amount' =>
                    '100000.00',
            ]
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'PAYMENT_ALLOCATION_CONFLICT'
            );
    }

    public function test_payment_and_invoice_currency_must_match(): void
    {
        $workspace = $this->workspace(
            'allocation-currency@example.test',
            'Allocation Currency'
        );

        $customer = $this->customer(
            $workspace,
            'Currency Customer'
        );

        $invoiceId =
            $this->createReceivableInvoice(
                $workspace,
                $customer,
                'INV-CURRENCY',
                '100000.00',
                'ISSUED',
                'USD'
            );

        $paymentId =
            $this->createPaymentWithAmount(
                $workspace,
                $customer,
                '100000.00'
            );

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/actions/verify'
        )->assertOk();

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/allocations',
            [
                'invoice_id' =>
                    $invoiceId,

                'amount' =>
                    '100000.00',
            ]
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'PAYMENT_ALLOCATION_CONFLICT'
            );
    }

    public function test_only_verified_payment_can_be_allocated(): void
    {
        $workspace = $this->workspace(
            'allocation-state@example.test',
            'Allocation State'
        );

        $customer = $this->customer(
            $workspace,
            'State Customer'
        );

        $invoiceId =
            $this->createReceivableInvoice(
                $workspace,
                $customer,
                'INV-PAYMENT-STATE',
                '100000.00',
                'ISSUED'
            );

        $pendingPayment =
            $this->createPaymentWithAmount(
                $workspace,
                $customer,
                '100000.00'
            );

        $this->postJson(
            '/api/v1/payments/'
            . $pendingPayment
            . '/allocations',
            [
                'invoice_id' =>
                    $invoiceId,

                'amount' =>
                    '100000.00',
            ]
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'PAYMENT_ALLOCATION_CONFLICT'
            );

        $rejectedPayment =
            $this->createPaymentWithAmount(
                $workspace,
                $customer,
                '100000.00'
            );

        $this->postJson(
            '/api/v1/payments/'
            . $rejectedPayment
            . '/actions/reject',
            [
                'reason' =>
                    'Tidak valid.',
            ]
        )->assertOk();

        $this->postJson(
            '/api/v1/payments/'
            . $rejectedPayment
            . '/allocations',
            [
                'invoice_id' =>
                    $invoiceId,

                'amount' =>
                    '100000.00',
            ]
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'PAYMENT_ALLOCATION_CONFLICT'
            );
    }

    public function test_draft_and_void_invoice_cannot_receive_allocation(): void
    {
        $workspace = $this->workspace(
            'allocation-invoice-state@example.test',
            'Allocation Invoice State'
        );

        $customer = $this->customer(
            $workspace,
            'Invoice State Customer'
        );

        $paymentId =
            $this->createPaymentWithAmount(
                $workspace,
                $customer,
                '200000.00'
            );

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/actions/verify'
        )->assertOk();

        $draftId =
            $this->createReceivableInvoice(
                $workspace,
                $customer,
                'INV-DRAFT-ALLOC',
                '100000.00',
                'DRAFT'
            );

        $voidId =
            $this->createReceivableInvoice(
                $workspace,
                $customer,
                'INV-VOID-ALLOC',
                '100000.00',
                'VOID'
            );

        foreach (
            [
                $draftId,
                $voidId,
            ] as $invoiceId
        ) {
            $this->postJson(
                '/api/v1/payments/'
                . $paymentId
                . '/allocations',
                [
                    'invoice_id' =>
                        $invoiceId,

                    'amount' =>
                        '50000.00',
                ]
            )
                ->assertStatus(409)
                ->assertJsonPath(
                    'error.code',
                    'PAYMENT_ALLOCATION_CONFLICT'
                );
        }
    }

    public function test_duplicate_payment_invoice_allocation_is_rejected(): void
    {
        $workspace = $this->workspace(
            'allocation-duplicate@example.test',
            'Allocation Duplicate'
        );

        $customer = $this->customer(
            $workspace,
            'Duplicate Customer'
        );

        $invoiceId =
            $this->createReceivableInvoice(
                $workspace,
                $customer,
                'INV-DUPLICATE',
                '200000.00',
                'ISSUED'
            );

        $paymentId =
            $this->createPaymentWithAmount(
                $workspace,
                $customer,
                '200000.00'
            );

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/actions/verify'
        )->assertOk();

        $payload = [
            'invoice_id' =>
                $invoiceId,

            'amount' =>
                '100000.00',
        ];

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/allocations',
            $payload
        )->assertCreated();

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/allocations',
            $payload
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'PAYMENT_ALLOCATION_CONFLICT'
            );

        $this->assertSame(
            1,
            DB::table(
                'payment_allocations'
            )
                ->where(
                    'payment_id',
                    $paymentId
                )
                ->where(
                    'invoice_id',
                    $invoiceId
                )
                ->count()
        );
    }

    public function test_foreign_tenant_invoice_cannot_receive_allocation(): void
    {
        $first = $this->workspace(
            'allocation-local@example.test',
            'Allocation Local'
        );

        $second = $this->workspace(
            'allocation-foreign@example.test',
            'Allocation Foreign'
        );

        $localCustomer =
            $this->customer(
                $first,
                'Local Customer'
            );

        $foreignCustomer =
            $this->customer(
                $second,
                'Foreign Customer'
            );

        $paymentId =
            $this->createPaymentWithAmount(
                $first,
                $localCustomer,
                '100000.00'
            );

        $foreignInvoiceId =
            $this->createReceivableInvoice(
                $second,
                $foreignCustomer,
                'INV-FOREIGN-ALLOC',
                '100000.00',
                'ISSUED'
            );

        $this->actingAsWorkspace(
            $first
        );

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/actions/verify'
        )->assertOk();

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/allocations',
            [
                'invoice_id' =>
                    $foreignInvoiceId,

                'amount' =>
                    '100000.00',
            ]
        )->assertNotFound();
    }

    public function test_allocation_requires_payment_verify_capability(): void
    {
        $workspace = $this->workspace(
            'allocation-capability@example.test',
            'Allocation Capability'
        );

        $customer = $this->customer(
            $workspace,
            'Capability Customer'
        );

        $invoiceId =
            $this->createReceivableInvoice(
                $workspace,
                $customer,
                'INV-ALLOC-CAP',
                '100000.00',
                'ISSUED'
            );

        $paymentId =
            $this->createPaymentWithAmount(
                $workspace,
                $customer,
                '100000.00'
            );

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/actions/verify'
        )->assertOk();

        $this->denyCapability(
            $workspace,
            'payment.verify'
        );

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/allocations',
            [
                'invoice_id' =>
                    $invoiceId,

                'amount' =>
                    '100000.00',
            ]
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );

        $this->assertDatabaseMissing(
            'payment_allocations',
            [
                'payment_id' =>
                    $paymentId,

                'invoice_id' =>
                    $invoiceId,
            ]
        );
    }

    public function test_verified_unallocated_payment_can_be_reversed(): void
    {
        $workspace = $this->workspace(
            'reverse-unallocated@example.test',
            'Reverse Unallocated'
        );

        $customer = $this->customer(
            $workspace,
            'Reverse Customer'
        );

        $paymentId =
            $this->createPaymentWithAmount(
                $workspace,
                $customer,
                '100000.00'
            );

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/actions/verify'
        )->assertOk();

        $response =
            $this->postJson(
                '/api/v1/payments/'
                . $paymentId
                . '/actions/reverse',
                [
                    'reason' =>
                        'Pembayaran dibatalkan.',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.payment.status',
                'REVERSED'
            )
            ->assertJsonPath(
                'data.reversal.amount',
                '100000.00'
            )
            ->assertJsonCount(
                0,
                'data.invoices'
            );

        $this->assertDatabaseHas(
            'payment_reversals',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],

                'payment_id' =>
                    $paymentId,

                'amount' =>
                    '100000.00',

                'reason' =>
                    'Pembayaran dibatalkan.',
            ]
        );
    }

    public function test_reversal_restores_paid_invoice_to_issued(): void
    {
        $workspace = $this->workspace(
            'reverse-paid@example.test',
            'Reverse Paid'
        );

        $customer = $this->customer(
            $workspace,
            'Paid Reverse Customer'
        );

        $invoiceId =
            $this->createReceivableInvoice(
                $workspace,
                $customer,
                'INV-REVERSE-PAID',
                '100000.00',
                'ISSUED'
            );

        $paymentId =
            $this->createPaymentWithAmount(
                $workspace,
                $customer,
                '100000.00'
            );

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/actions/verify'
        )->assertOk();

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/allocations',
            [
                'invoice_id' =>
                    $invoiceId,

                'amount' =>
                    '100000.00',
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.invoice.status',
                'PAID'
            );

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/actions/reverse',
            [
                'reason' =>
                    'Transfer dikembalikan.',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.payment.status',
                'REVERSED'
            )
            ->assertJsonPath(
                'data.invoices.0.status',
                'ISSUED'
            )
            ->assertJsonPath(
                'data.invoices.0.paid_amount',
                '0.00'
            )
            ->assertJsonPath(
                'data.invoices.0.outstanding_amount',
                '100000.00'
            );

        $this->assertDatabaseHas(
            'invoices',
            [
                'id' =>
                    $invoiceId,

                'status' =>
                    'ISSUED',

                'paid_amount' =>
                    '0.00',

                'outstanding_amount' =>
                    '100000.00',
            ]
        );

        $this->assertDatabaseHas(
            'invoice_status_history',
            [
                'invoice_id' =>
                    $invoiceId,

                'from_state' =>
                    'PAID',

                'to_state' =>
                    'ISSUED',

                'source' =>
                    'PAYMENT_REVERSAL',

                'reason' =>
                    'Transfer dikembalikan.',
            ]
        );
    }

    public function test_reversal_preserves_other_verified_payment_contribution(): void
    {
        $workspace = $this->workspace(
            'reverse-other-payment@example.test',
            'Reverse Other Payment'
        );

        $customer = $this->customer(
            $workspace,
            'Other Payment Customer'
        );

        $invoiceId =
            $this->createReceivableInvoice(
                $workspace,
                $customer,
                'INV-REVERSE-OTHER',
                '1000000.00',
                'ISSUED'
            );

        $first =
            $this->createPaymentWithAmount(
                $workspace,
                $customer,
                '400000.00'
            );

        $second =
            $this->createPaymentWithAmount(
                $workspace,
                $customer,
                '600000.00'
            );

        foreach (
            [
                $first => '400000.00',
                $second => '600000.00',
            ] as $paymentId => $amount
        ) {
            $this->postJson(
                '/api/v1/payments/'
                . $paymentId
                . '/actions/verify'
            )->assertOk();

            $this->postJson(
                '/api/v1/payments/'
                . $paymentId
                . '/allocations',
                [
                    'invoice_id' =>
                        $invoiceId,

                    'amount' =>
                        $amount,
                ]
            )->assertCreated();
        }

        $this->assertDatabaseHas(
            'invoices',
            [
                'id' =>
                    $invoiceId,

                'status' =>
                    'PAID',

                'paid_amount' =>
                    '1000000.00',
            ]
        );

        $this->postJson(
            '/api/v1/payments/'
            . $second
            . '/actions/reverse',
            [
                'reason' =>
                    'Pembayaran kedua dibalik.',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.invoices.0.status',
                'PARTIALLY_PAID'
            )
            ->assertJsonPath(
                'data.invoices.0.paid_amount',
                '400000.00'
            )
            ->assertJsonPath(
                'data.invoices.0.outstanding_amount',
                '600000.00'
            );
    }

    public function test_split_payment_reversal_recalculates_all_invoices(): void
    {
        $workspace = $this->workspace(
            'reverse-split@example.test',
            'Reverse Split'
        );

        $customer = $this->customer(
            $workspace,
            'Split Customer'
        );

        $invoiceA =
            $this->createReceivableInvoice(
                $workspace,
                $customer,
                'INV-SPLIT-A',
                '400000.00',
                'ISSUED'
            );

        $invoiceB =
            $this->createReceivableInvoice(
                $workspace,
                $customer,
                'INV-SPLIT-B',
                '600000.00',
                'ISSUED'
            );

        $paymentId =
            $this->createPaymentWithAmount(
                $workspace,
                $customer,
                '1000000.00'
            );

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/actions/verify'
        )->assertOk();

        foreach (
            [
                $invoiceA => '400000.00',
                $invoiceB => '600000.00',
            ] as $invoiceId => $amount
        ) {
            $this->postJson(
                '/api/v1/payments/'
                . $paymentId
                . '/allocations',
                [
                    'invoice_id' =>
                        $invoiceId,

                    'amount' =>
                        $amount,
                ]
            )->assertCreated();
        }

        $response =
            $this->postJson(
                '/api/v1/payments/'
                . $paymentId
                . '/actions/reverse',
                [
                    'reason' =>
                        'Pembayaran split dibatalkan.',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonCount(
                2,
                'data.invoices'
            );

        foreach (
            [
                $invoiceA => '400000.00',
                $invoiceB => '600000.00',
            ] as $invoiceId => $total
        ) {
            $this->assertDatabaseHas(
                'invoices',
                [
                    'id' =>
                        $invoiceId,

                    'status' =>
                        'ISSUED',

                    'paid_amount' =>
                        '0.00',

                    'outstanding_amount' =>
                        $total,
                ]
            );
        }
    }

    public function test_payment_cannot_be_reversed_twice(): void
    {
        $workspace = $this->workspace(
            'reverse-twice@example.test',
            'Reverse Twice'
        );

        $customer = $this->customer(
            $workspace,
            'Reverse Twice Customer'
        );

        $paymentId =
            $this->createPaymentWithAmount(
                $workspace,
                $customer,
                '100000.00'
            );

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/actions/verify'
        )->assertOk();

        $payload = [
            'reason' =>
                'Pembalikan pertama.',
        ];

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/actions/reverse',
            $payload
        )->assertOk();

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/actions/reverse',
            $payload
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'INVALID_TRANSITION'
            );

        $this->assertSame(
            1,
            DB::table(
                'payment_reversals'
            )
                ->where(
                    'payment_id',
                    $paymentId
                )
                ->count()
        );
    }

    public function test_only_verified_payment_can_be_reversed(): void
    {
        $workspace = $this->workspace(
            'reverse-state@example.test',
            'Reverse State'
        );

        $customer = $this->customer(
            $workspace,
            'Reverse State Customer'
        );

        $pending =
            $this->createPaymentWithAmount(
                $workspace,
                $customer,
                '100000.00'
            );

        $this->postJson(
            '/api/v1/payments/'
            . $pending
            . '/actions/reverse',
            [
                'reason' =>
                    'Tidak boleh.',
            ]
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'INVALID_TRANSITION'
            );

        $rejected =
            $this->createPaymentWithAmount(
                $workspace,
                $customer,
                '100000.00'
            );

        $this->postJson(
            '/api/v1/payments/'
            . $rejected
            . '/actions/reject',
            [
                'reason' =>
                    'Ditolak.',
            ]
        )->assertOk();

        $this->postJson(
            '/api/v1/payments/'
            . $rejected
            . '/actions/reverse',
            [
                'reason' =>
                    'Tidak boleh.',
            ]
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'INVALID_TRANSITION'
            );
    }

    public function test_reversal_requires_reason(): void
    {
        $workspace = $this->workspace(
            'reverse-reason@example.test',
            'Reverse Reason'
        );

        $customer = $this->customer(
            $workspace,
            'Reverse Reason Customer'
        );

        $paymentId =
            $this->createPaymentWithAmount(
                $workspace,
                $customer,
                '100000.00'
            );

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/actions/verify'
        )->assertOk();

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/actions/reverse',
            [
                'reason' => '   ',
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
                            'reason',
                        ],
                    ],
                ],
            ]);

        $this->assertDatabaseMissing(
            'payment_reversals',
            [
                'payment_id' =>
                    $paymentId,
            ]
        );
    }

    public function test_foreign_tenant_cannot_reverse_payment(): void
    {
        $first = $this->workspace(
            'reverse-local@example.test',
            'Reverse Local'
        );

        $second = $this->workspace(
            'reverse-foreign@example.test',
            'Reverse Foreign'
        );

        $foreignCustomer =
            $this->customer(
                $second,
                'Foreign Reverse Customer'
            );

        $paymentId =
            $this->createPaymentWithAmount(
                $second,
                $foreignCustomer,
                '100000.00'
            );

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/actions/verify'
        )->assertOk();

        $this->actingAsWorkspace(
            $first
        );

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/actions/reverse',
            [
                'reason' =>
                    'Tidak boleh.',
            ]
        )->assertNotFound();
    }

    public function test_reversal_requires_payment_reverse_capability(): void
    {
        $workspace = $this->workspace(
            'reverse-capability@example.test',
            'Reverse Capability'
        );

        $customer = $this->customer(
            $workspace,
            'Reverse Capability Customer'
        );

        $paymentId =
            $this->createPaymentWithAmount(
                $workspace,
                $customer,
                '100000.00'
            );

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/actions/verify'
        )->assertOk();

        $this->denyCapability(
            $workspace,
            'payment.reverse'
        );

        $this->postJson(
            '/api/v1/payments/'
            . $paymentId
            . '/actions/reverse',
            [
                'reason' =>
                    'Tidak punya akses.',
            ]
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );

        $this->assertDatabaseMissing(
            'payment_reversals',
            [
                'payment_id' =>
                    $paymentId,
            ]
        );
    }

    private function createPaymentWithAmount(
        array $workspace,
        Customer $customer,
        string $amount
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
                        $amount,

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

    private function createReceivableInvoice(
        array $workspace,
        Customer $customer,
        string $number,
        string $total,
        string $status = 'ISSUED',
        string $currency = 'IDR'
    ): string {
        $id =
            (string) \Illuminate\Support\Str::ulid();

        $isReceivable =
            in_array(
                $status,
                [
                    'ISSUED',
                    'PARTIALLY_PAID',
                ],
                true
            );

        DB::table('invoices')->insert([
            'id' =>
                $id,

            'tenant_id' =>
                $workspace['tenant_id'],

            'invoice_number' =>
                $number,

            'customer_id' =>
                $customer->id,

            'project_id' =>
                null,

            'source_quotation_id' =>
                null,

            'source_quotation_version_id' =>
                null,

            'status' =>
                $status,

            'issued_at' =>
                $status === 'DRAFT'
                    ? null
                    : now(),

            'due_at' =>
                null,

            'currency' =>
                $currency,

            'subtotal' =>
                $total,

            'discount_total' =>
                '0.00',

            'tax_total' =>
                '0.00',

            'total' =>
                $total,

            'paid_amount' =>
                $status === 'PAID'
                    ? $total
                    : '0.00',

            'outstanding_amount' =>
                $isReceivable
                    ? $total
                    : '0.00',

            'notes' =>
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
