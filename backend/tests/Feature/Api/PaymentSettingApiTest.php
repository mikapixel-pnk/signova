<?php

namespace Tests\Feature\Api;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use App\Models\BusinessProfile;
use App\Models\User;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentSettingApiTest extends TestCase
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

    public function test_get_returns_virtual_defaults_without_creating_row(): void
    {
        $workspace = $this->workspace(
            'payment-settings-default@example.test',
            'Payment Settings Default'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->assertDatabaseMissing(
            'tenant_payment_settings',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],
            ]
        );

        $this->getJson(
            '/api/v1/settings/payment'
        )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.bank_transfer_enabled',
                false
            )
            ->assertJsonPath(
                'data.static_qr_enabled',
                false
            )
            ->assertJsonPath(
                'data.has_static_qr',
                false
            )
            ->assertJsonPath(
                'data.midtrans_enabled',
                false
            )
            ->assertJsonPath(
                'data.partial_payment_enabled',
                true
            );

        $this->assertDatabaseMissing(
            'tenant_payment_settings',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],
            ]
        );
    }

    public function test_owner_can_update_payment_settings(): void
    {
        $workspace = $this->workspace(
            'payment-settings-update@example.test',
            'Payment Settings Update'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->patchJson(
            '/api/v1/settings/payment',
            [
                'bank_transfer_enabled' =>
                    true,

                'bank_name' =>
                    'BCA',

                'bank_account_number' =>
                    '1234567890',

                'bank_account_name' =>
                    'PT Signova Test',

                'partial_payment_enabled' =>
                    false,
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.bank_transfer_enabled',
                true
            )
            ->assertJsonPath(
                'data.bank_name',
                'BCA'
            )
            ->assertJsonPath(
                'data.bank_account_number',
                '1234567890'
            )
            ->assertJsonPath(
                'data.bank_account_name',
                'PT Signova Test'
            )
            ->assertJsonPath(
                'data.partial_payment_enabled',
                false
            );

        $this->assertDatabaseHas(
            'tenant_payment_settings',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],

                'bank_transfer_enabled' =>
                    true,

                'bank_name' =>
                    'BCA',

                'partial_payment_enabled' =>
                    false,
            ]
        );
    }

    public function test_blank_bank_fields_are_normalized_to_null(): void
    {
        $workspace = $this->workspace(
            'payment-settings-null@example.test',
            'Payment Settings Null'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->patchJson(
            '/api/v1/settings/payment',
            [
                'bank_name' => '   ',
                'bank_account_number' => '',
                'bank_account_name' => '   ',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.bank_name',
                null
            )
            ->assertJsonPath(
                'data.bank_account_number',
                null
            )
            ->assertJsonPath(
                'data.bank_account_name',
                null
            );

        $this->assertDatabaseHas(
            'tenant_payment_settings',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],

                'bank_name' => null,
                'bank_account_number' => null,
                'bank_account_name' => null,
            ]
        );
    }

    public function test_owner_can_upload_private_static_qr(): void
    {
        $workspace = $this->workspace(
            'payment-qr-upload@example.test',
            'Payment QR Upload'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->post(
            '/api/v1/settings/payment/static-qr',
            [
                'qr' =>
                    UploadedFile::fake()->image(
                        'qris.png',
                        800,
                        800
                    ),
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.static_qr_enabled',
                true
            )
            ->assertJsonPath(
                'data.has_static_qr',
                true
            )
            ->assertJsonPath(
                'data.static_qr.mime_type',
                'image/png'
            );

        $file = DB::table('files')
            ->where(
                'tenant_id',
                $workspace['tenant_id']
            )
            ->where(
                'purpose',
                'PAYMENT_QR'
            )
            ->first();

        $this->assertNotNull(
            $file
        );

        $this->assertStringStartsWith(
            'tenants/'
                . $workspace['tenant_id']
                . '/payment-qr/',
            $file->object_key
        );

        $this->assertSame(
            'PRIVATE',
            $file->visibility
        );

        Storage::disk('local')
            ->assertExists(
                $file->object_key
            );

        $this->assertDatabaseHas(
            'tenant_payment_settings',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],

                'static_qr_enabled' =>
                    true,

                'static_qr_file_id' =>
                    $file->id,
            ]
        );
    }

    public function test_uploading_new_static_qr_replaces_old_file(): void
    {
        $workspace = $this->workspace(
            'payment-qr-replace@example.test',
            'Payment QR Replace'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->post(
            '/api/v1/settings/payment/static-qr',
            [
                'qr' =>
                    UploadedFile::fake()->image(
                        'old.png',
                        600,
                        600
                    ),
            ]
        )->assertOk();

        $oldFile = DB::table('files')
            ->where(
                'tenant_id',
                $workspace['tenant_id']
            )
            ->where(
                'purpose',
                'PAYMENT_QR'
            )
            ->first();

        $this->assertNotNull(
            $oldFile
        );

        $this->post(
            '/api/v1/settings/payment/static-qr',
            [
                'qr' =>
                    UploadedFile::fake()->image(
                        'new.png',
                        700,
                        700
                    ),
            ]
        )->assertOk();

        $newFile = DB::table('files')
            ->where(
                'tenant_id',
                $workspace['tenant_id']
            )
            ->where(
                'purpose',
                'PAYMENT_QR'
            )
            ->first();

        $this->assertNotNull(
            $newFile
        );

        $this->assertNotSame(
            $oldFile->id,
            $newFile->id
        );

        Storage::disk('local')
            ->assertMissing(
                $oldFile->object_key
            );

        Storage::disk('local')
            ->assertExists(
                $newFile->object_key
            );

        $this->assertDatabaseMissing(
            'files',
            [
                'id' =>
                    $oldFile->id,
            ]
        );
    }

    public function test_owner_can_preview_private_static_qr(): void
    {
        $workspace = $this->workspace(
            'payment-qr-preview@example.test',
            'Payment QR Preview'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->post(
            '/api/v1/settings/payment/static-qr',
            [
                'qr' =>
                    UploadedFile::fake()->image(
                        'preview.png',
                        800,
                        800
                    ),
            ]
        )->assertOk();

        $response = $this->get(
            '/api/v1/settings/payment/static-qr'
        );

        $response
            ->assertOk()
            ->assertHeader(
                'Content-Type',
                'image/png'
            )
            ->assertHeader(
                'X-Content-Type-Options',
                'nosniff'
            );

        $cacheControl =
            $response->headers->get(
                'Cache-Control'
            );

        $this->assertIsString(
            $cacheControl
        );

        $this->assertStringContainsString(
            'private',
            $cacheControl
        );

        $this->assertStringContainsString(
            'no-store',
            $cacheControl
        );
    }

    public function test_owner_can_remove_static_qr(): void
    {
        $workspace = $this->workspace(
            'payment-qr-delete@example.test',
            'Payment QR Delete'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->post(
            '/api/v1/settings/payment/static-qr',
            [
                'qr' =>
                    UploadedFile::fake()->image(
                        'delete.png',
                        800,
                        800
                    ),
            ]
        )->assertOk();

        $file = DB::table('files')
            ->where(
                'tenant_id',
                $workspace['tenant_id']
            )
            ->where(
                'purpose',
                'PAYMENT_QR'
            )
            ->first();

        $this->assertNotNull(
            $file
        );

        $this->deleteJson(
            '/api/v1/settings/payment/static-qr'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.static_qr_enabled',
                false
            )
            ->assertJsonPath(
                'data.has_static_qr',
                false
            );

        Storage::disk('local')
            ->assertMissing(
                $file->object_key
            );

        $this->assertDatabaseMissing(
            'files',
            [
                'id' => $file->id,
            ]
        );

        $this->assertDatabaseHas(
            'tenant_payment_settings',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],

                'static_qr_enabled' =>
                    false,

                'static_qr_file_id' =>
                    null,
            ]
        );
    }

    public function test_static_qr_rejects_unsupported_file_type(): void
    {
        $workspace = $this->workspace(
            'payment-qr-invalid@example.test',
            'Payment QR Invalid'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->post(
            '/api/v1/settings/payment/static-qr',
            [
                'qr' =>
                    UploadedFile::fake()->create(
                        'qr.svg',
                        5,
                        'image/svg+xml'
                    ),
            ]
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );

        $this->assertDatabaseMissing(
            'tenant_payment_settings',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],
            ]
        );

        $this->assertDatabaseCount(
            'files',
            0
        );
    }

    public function test_payment_settings_capabilities_are_enforced(): void
    {
        $workspace = $this->workspace(
            'payment-settings-denied@example.test',
            'Payment Settings Denied'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->denyCapability(
            $workspace,
            'settings.manage'
        );

        $this->patchJson(
            '/api/v1/settings/payment',
            [
                'bank_transfer_enabled' =>
                    true,
            ]
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );

        $this->post(
            '/api/v1/settings/payment/static-qr',
            [
                'qr' =>
                    UploadedFile::fake()->image(
                        'denied.png'
                    ),
            ]
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );
    }

    public function test_same_tenant_businesses_have_separate_payment_settings(): void
    {
        $workspace =
            $this->workspace(
                'payment-settings-multi-business@example.test',
                'Payment Settings Multi Business'
            );

        $secondBusiness =
            BusinessProfile::query()->create([
                'tenant_id' =>
                    $workspace['tenant_id'],

                'name' =>
                    'Usaha Kedua',

                'is_default' =>
                    false,

                'status' =>
                    'ACTIVE',
            ]);

        $first =
            $workspace;

        $second =
            $workspace;

        $second['business_id'] =
            $secondBusiness->id;

        $this->actingAsWorkspace(
            $first
        );

        $this->patchJson(
            '/api/v1/settings/payment',
            [
                'bank_transfer_enabled' =>
                    true,

                'bank_name' =>
                    'BCA',

                'bank_account_number' =>
                    '1111111111',

                'bank_account_name' =>
                    'Usaha Pertama',
            ]
        )->assertOk();

        $this->actingAsWorkspace(
            $second
        );

        $this->patchJson(
            '/api/v1/settings/payment',
            [
                'bank_transfer_enabled' =>
                    true,

                'bank_name' =>
                    'MANDIRI',

                'bank_account_number' =>
                    '2222222222',

                'bank_account_name' =>
                    'Usaha Kedua',
            ]
        )->assertOk();

        $this->actingAsWorkspace(
            $first
        );

        $this->getJson(
            '/api/v1/settings/payment'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.bank_name',
                'BCA'
            )
            ->assertJsonPath(
                'data.bank_account_number',
                '1111111111'
            )
            ->assertJsonPath(
                'data.bank_account_name',
                'Usaha Pertama'
            );

        $this->actingAsWorkspace(
            $second
        );

        $this->getJson(
            '/api/v1/settings/payment'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.bank_name',
                'MANDIRI'
            )
            ->assertJsonPath(
                'data.bank_account_number',
                '2222222222'
            )
            ->assertJsonPath(
                'data.bank_account_name',
                'Usaha Kedua'
            );

        $this->assertDatabaseHas(
            'tenant_payment_settings',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],

                'business_id' =>
                    $first['business_id'],

                'bank_name' =>
                    'BCA',
            ]
        );

        $this->assertDatabaseHas(
            'tenant_payment_settings',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],

                'business_id' =>
                    $second['business_id'],

                'bank_name' =>
                    'MANDIRI',
            ]
        );
    }

    public function test_tenant_cannot_preview_another_tenant_static_qr(): void
    {
        $first = $this->workspace(
            'payment-qr-first@example.test',
            'Payment QR First'
        );

        $second = $this->workspace(
            'payment-qr-second@example.test',
            'Payment QR Second'
        );

        $this->actingAsWorkspace(
            $second
        );

        $this->post(
            '/api/v1/settings/payment/static-qr',
            [
                'qr' =>
                    UploadedFile::fake()->image(
                        'second.png',
                        800,
                        800
                    ),
            ]
        )->assertOk();

        $this->actingAsWorkspace(
            $first
        );

        $this->get(
            '/api/v1/settings/payment/static-qr'
        )->assertNotFound();
    }

    private function workspace(
        string $email,
        string $businessName
    ): array {
        $workspace = app(
            CreateTenantWorkspaceAction::class
        )->execute([
            'name' => 'Owner',
            'email' => $email,
            'password' => 'password',
            'tenant_name' => $businessName,
            'timezone' => 'Asia/Jakarta',
        ]);

        return [
            'user_id' =>
                $workspace['user_id'],

            'tenant_id' =>
                $workspace['tenant_id'],

            'business_id' =>
                $workspace['business_id'],

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

        $this->withHeader(
            'X-Signova-Business',
            $workspace['business_id']
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
                'effect' => 'DENY',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
