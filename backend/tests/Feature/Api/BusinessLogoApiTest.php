<?php

namespace Tests\Feature\Api;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use App\Models\User;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class BusinessLogoApiTest extends TestCase
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

    public function test_owner_can_upload_private_business_logo(): void
    {
        $workspace = $this->workspace(
            'logo-upload@example.test',
            'Logo Upload'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $response = $this->post(
            '/api/v1/settings/business-profile/logo',
            [
                'logo' =>
                    UploadedFile::fake()->image(
                        'logo.png',
                        1200,
                        400
                    ),
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.has_logo',
                true
            );

        $file =
            DB::table('files')
                ->where(
                    'tenant_id',
                    $workspace['tenant_id']
                )
                ->where(
                    'purpose',
                    'BUSINESS_LOGO'
                )
                ->first();

        $this->assertNotNull(
            $file
        );

        $this->assertStringStartsWith(
            'tenants/'
                . $workspace['tenant_id']
                . '/business-logos/',
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
            'business_profiles',
            [
                'id' =>
                    $workspace['business_id'],

                'tenant_id' =>
                    $workspace['tenant_id'],

                'logo_file_id' =>
                    $file->id,
            ]
        );
    }

    public function test_uploading_new_logo_replaces_old_file(): void
    {
        $workspace = $this->workspace(
            'logo-replace@example.test',
            'Logo Replace'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->post(
            '/api/v1/settings/business-profile/logo',
            [
                'logo' =>
                    UploadedFile::fake()->image(
                        'old.png',
                        900,
                        300
                    ),
            ]
        )->assertOk();

        $oldFile =
            DB::table('files')
                ->where(
                    'tenant_id',
                    $workspace['tenant_id']
                )
                ->where(
                    'purpose',
                    'BUSINESS_LOGO'
                )
                ->first();

        $this->assertNotNull(
            $oldFile
        );

        $this->post(
            '/api/v1/settings/business-profile/logo',
            [
                'logo' =>
                    UploadedFile::fake()->image(
                        'new.png',
                        800,
                        800
                    ),
            ]
        )->assertOk();

        $newFile =
            DB::table('files')
                ->where(
                    'tenant_id',
                    $workspace['tenant_id']
                )
                ->where(
                    'purpose',
                    'BUSINESS_LOGO'
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
            'business_profiles',
            [
                'id' =>
                    $workspace['business_id'],

                'logo_file_id' =>
                    $newFile->id,
            ]
        );
    }

    public function test_owner_can_preview_private_business_logo(): void
    {
        $workspace = $this->workspace(
            'logo-preview@example.test',
            'Logo Preview'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->post(
            '/api/v1/settings/business-profile/logo',
            [
                'logo' =>
                    UploadedFile::fake()->image(
                        'preview.png',
                        1000,
                        400
                    ),
            ]
        )->assertOk();

        $response =
            $this->get(
                '/api/v1/settings/business-profile/logo'
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

    public function test_owner_can_remove_business_logo_and_object(): void
    {
        $workspace = $this->workspace(
            'logo-delete@example.test',
            'Logo Delete'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->post(
            '/api/v1/settings/business-profile/logo',
            [
                'logo' =>
                    UploadedFile::fake()->image(
                        'delete.png',
                        900,
                        300
                    ),
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
                    'BUSINESS_LOGO'
                )
                ->first();

        $this->assertNotNull(
            $file
        );

        $this->deleteJson(
            '/api/v1/settings/business-profile/logo'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.has_logo',
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
            'business_profiles',
            [
                'id' =>
                    $workspace['business_id'],

                'logo_file_id' =>
                    null,
            ]
        );
    }

    public function test_logo_upload_rejects_unsupported_file_type(): void
    {
        $workspace = $this->workspace(
            'logo-invalid@example.test',
            'Logo Invalid'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->post(
            '/api/v1/settings/business-profile/logo',
            [
                'logo' =>
                    UploadedFile::fake()->create(
                        'logo.svg',
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

    public function test_logo_upload_rejects_file_larger_than_five_megabytes(): void
    {
        $workspace = $this->workspace(
            'logo-large@example.test',
            'Logo Large'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $file =
            UploadedFile::fake()
                ->image(
                    'large.png',
                    100,
                    100
                )
                ->size(5121);

        $this->post(
            '/api/v1/settings/business-profile/logo',
            [
                'logo' => $file,
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

    public function test_logo_is_isolated_between_businesses_in_same_tenant(): void
    {
        $workspace = $this->workspace(
            'logo-business-isolation@example.test',
            'Logo Business Isolation'
        );

        $secondBusinessId =
            (string) Str::ulid();

        DB::table(
            'business_profiles'
        )->insert([
            'id' =>
                $secondBusinessId,

            'tenant_id' =>
                $workspace['tenant_id'],

            'name' =>
                'Usaha Kedua',

            'is_default' =>
                false,

            'status' =>
                'ACTIVE',

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        $second =
            $workspace;

        $second['business_id'] =
            $secondBusinessId;

        $this->actingAsWorkspace(
            $second
        );

        $this->post(
            '/api/v1/settings/business-profile/logo',
            [
                'logo' =>
                    UploadedFile::fake()->image(
                        'second-business.png',
                        900,
                        300
                    ),
            ]
        )->assertOk();

        $secondLogoId =
            DB::table(
                'business_profiles'
            )
                ->where(
                    'id',
                    $secondBusinessId
                )
                ->value(
                    'logo_file_id'
                );

        $this->assertIsString(
            $secondLogoId
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->get(
            '/api/v1/settings/business-profile/logo'
        )->assertNotFound();

        $this->deleteJson(
            '/api/v1/settings/business-profile/logo'
        )->assertOk();

        $this->assertDatabaseHas(
            'business_profiles',
            [
                'id' =>
                    $secondBusinessId,

                'tenant_id' =>
                    $workspace['tenant_id'],

                'logo_file_id' =>
                    $secondLogoId,
            ]
        );

        $this->assertDatabaseHas(
            'files',
            [
                'id' =>
                    $secondLogoId,

                'tenant_id' =>
                    $workspace['tenant_id'],

                'purpose' =>
                    'BUSINESS_LOGO',
            ]
        );
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
}
