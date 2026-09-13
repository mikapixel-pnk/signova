<?php

namespace Tests\Feature\Api;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use App\Models\User;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentSettingApiTest extends TestCase
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

    public function test_owner_can_read_and_update_document_settings(): void
    {
        $workspace = $this->workspace(
            'settings@example.test',
            'Settings Test'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->getJson(
            '/api/v1/settings/document'
        )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.has_signature_image',
                false
            );

        $this->patchJson(
            '/api/v1/settings/document',
            [
                'business_name' =>
                    'PT Signova Reklame',

                'invoice_footnote' =>
                    'Terima kasih atas kepercayaan Anda.',

                'signature_name' =>
                    'Budi Santoso',

                'signature_title' =>
                    'Finance Manager',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.business_name',
                'PT Signova Reklame'
            )
            ->assertJsonPath(
                'data.signature_name',
                'Budi Santoso'
            );

        $this->assertDatabaseHas(
            'tenant_document_settings',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],

                'business_name' =>
                    'PT Signova Reklame',

                'signature_title' =>
                    'Finance Manager',
            ]
        );
    }


    public function test_optional_signature_and_note_fields_may_be_blank(): void
    {
        $workspace = $this->workspace(
            'settings-optional@example.test',
            'Settings Optional'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $response = $this->patchJson(
            '/api/v1/settings/document',
            [
                'invoice_footnote' =>
                    '   ',

                'signature_name' =>
                    '',

                'signature_title' =>
                    '   ',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.invoice_footnote',
                null
            )
            ->assertJsonPath(
                'data.signature_name',
                null
            )
            ->assertJsonPath(
                'data.signature_title',
                null
            )
            ->assertJsonPath(
                'data.has_signature_image',
                false
            );

        $this->assertDatabaseHas(
            'tenant_document_settings',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],

                'invoice_footnote' =>
                    null,

                'signature_name' =>
                    null,

                'signature_title' =>
                    null,

                'signature_image_file_id' =>
                    null,
            ]
        );
    }

    public function test_owner_can_upload_private_signature_image(): void
    {
        $workspace = $this->workspace(
            'signature-upload@example.test',
            'Signature Upload'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $response = $this->post(
            '/api/v1/settings/document/signature',
            [
                'signature' =>
                    UploadedFile::fake()->image(
                        'ttd.png',
                        600,
                        200
                    ),
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.has_signature_image',
                true
            )
            ->assertJsonPath(
                'data.signature_image.mime_type',
                'image/png'
            );

        $file = DB::table('files')
            ->where(
                'tenant_id',
                $workspace['tenant_id']
            )
            ->where(
                'purpose',
                'DOCUMENT_SIGNATURE'
            )
            ->first();

        $this->assertNotNull($file);

        $this->assertStringStartsWith(
            'tenants/'
                . $workspace['tenant_id']
                . '/document-signatures/',
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
            'tenant_document_settings',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],

                'signature_image_file_id' =>
                    $file->id,
            ]
        );
    }

    public function test_uploading_new_signature_replaces_old_file(): void
    {
        $workspace = $this->workspace(
            'signature-replace@example.test',
            'Signature Replace'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->post(
            '/api/v1/settings/document/signature',
            [
                'signature' =>
                    UploadedFile::fake()->image(
                        'old.png',
                        500,
                        180
                    ),
            ]
        )->assertOk();

        $oldFile = DB::table('files')
            ->where(
                'tenant_id',
                $workspace['tenant_id']
            )
            ->first();

        $this->assertNotNull(
            $oldFile
        );

        Storage::disk('local')
            ->assertExists(
                $oldFile->object_key
            );

        $this->post(
            '/api/v1/settings/document/signature',
            [
                'signature' =>
                    UploadedFile::fake()->image(
                        'new.png',
                        700,
                        220
                    ),
            ]
        )->assertOk();

        $newFile = DB::table('files')
            ->where(
                'tenant_id',
                $workspace['tenant_id']
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

        $this->assertDatabaseHas(
            'tenant_document_settings',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],

                'signature_image_file_id' =>
                    $newFile->id,
            ]
        );
    }

    public function test_owner_can_preview_private_signature(): void
    {
        $workspace = $this->workspace(
            'signature-preview@example.test',
            'Signature Preview'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->post(
            '/api/v1/settings/document/signature',
            [
                'signature' =>
                    UploadedFile::fake()->image(
                        'preview.png',
                        600,
                        200
                    ),
            ]
        )->assertOk();

        $response = $this->get(
            '/api/v1/settings/document/signature'
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

    public function test_owner_can_remove_signature_and_object(): void
    {
        $workspace = $this->workspace(
            'signature-delete@example.test',
            'Signature Delete'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->post(
            '/api/v1/settings/document/signature',
            [
                'signature' =>
                    UploadedFile::fake()->image(
                        'delete.png',
                        500,
                        180
                    ),
            ]
        )->assertOk();

        $file = DB::table('files')
            ->where(
                'tenant_id',
                $workspace['tenant_id']
            )
            ->first();

        $this->assertNotNull(
            $file
        );

        $this->deleteJson(
            '/api/v1/settings/document/signature'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.has_signature_image',
                false
            );

        Storage::disk('local')
            ->assertMissing(
                $file->object_key
            );

        $this->assertDatabaseMissing(
            'files',
            [
                'id' =>
                    $file->id,
            ]
        );

        $this->assertDatabaseHas(
            'tenant_document_settings',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],

                'signature_image_file_id' =>
                    null,
            ]
        );
    }

    public function test_signature_upload_rejects_unsupported_file_type(): void
    {
        $workspace = $this->workspace(
            'signature-invalid@example.test',
            'Signature Invalid'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->post(
            '/api/v1/settings/document/signature',
            [
                'signature' =>
                    UploadedFile::fake()->create(
                        'signature.svg',
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

        $this->assertDatabaseCount(
            'files',
            0
        );
    }

    public function test_tenant_cannot_preview_another_tenant_signature(): void
    {
        $first = $this->workspace(
            'signature-first@example.test',
            'Signature First'
        );

        $second = $this->workspace(
            'signature-second@example.test',
            'Signature Second'
        );

        $this->actingAsWorkspace(
            $second
        );

        $this->post(
            '/api/v1/settings/document/signature',
            [
                'signature' =>
                    UploadedFile::fake()->image(
                        'second.png',
                        600,
                        200
                    ),
            ]
        )->assertOk();

        $this->actingAsWorkspace(
            $first
        );

        $this->get(
            '/api/v1/settings/document/signature'
        )->assertNotFound();
    }

    public function test_settings_capabilities_are_enforced(): void
    {
        $workspace = $this->workspace(
            'settings-denied@example.test',
            'Settings Denied'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->denyCapability(
            $workspace,
            'settings.manage'
        );

        $this->patchJson(
            '/api/v1/settings/document',
            [
                'business_name' =>
                    'Tidak Boleh',
            ]
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );

        $this->post(
            '/api/v1/settings/document/signature',
            [
                'signature' =>
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
        $roleId = DB::table('roles')
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
