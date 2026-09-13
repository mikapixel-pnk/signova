<?php

namespace Tests\Feature\Tenancy;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use App\Models\User;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FileFoundationIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_file_uploader_must_belong_to_same_tenant(): void
    {
        $first = $this->workspace(
            'file-first@example.test',
            'File First'
        );

        $second = $this->workspace(
            'file-second@example.test',
            'File Second'
        );

        $this->expectException(
            \Illuminate\Database\QueryException::class
        );

        $this->insertFile(
            $first['tenant_id'],
            $second['user_id'],
            'DOCUMENT_SIGNATURE',
            'tenants/'
                . $first['tenant_id']
                . '/document-signatures/'
                . Str::ulid()
                . '.png'
        );
    }

    public function test_same_object_key_can_exist_in_different_tenants(): void
    {
        $first = $this->workspace(
            'file-key-first@example.test',
            'File Key First'
        );

        $second = $this->workspace(
            'file-key-second@example.test',
            'File Key Second'
        );

        $key =
            'shared-test-key/file.png';

        $this->insertFile(
            $first['tenant_id'],
            $first['user_id'],
            'DOCUMENT_SIGNATURE',
            $key
        );

        $this->insertFile(
            $second['tenant_id'],
            $second['user_id'],
            'DOCUMENT_SIGNATURE',
            $key
        );

        $this->assertDatabaseCount(
            'files',
            2
        );
    }

    public function test_same_object_key_cannot_repeat_inside_tenant(): void
    {
        $workspace = $this->workspace(
            'file-key-duplicate@example.test',
            'File Key Duplicate'
        );

        $key =
            'tenants/'
            . $workspace['tenant_id']
            . '/document-signatures/fixed.png';

        $this->insertFile(
            $workspace['tenant_id'],
            $workspace['user_id'],
            'DOCUMENT_SIGNATURE',
            $key
        );

        $this->expectException(
            \Illuminate\Database\QueryException::class
        );

        $this->insertFile(
            $workspace['tenant_id'],
            $workspace['user_id'],
            'DOCUMENT_SIGNATURE',
            $key
        );
    }

    public function test_signature_file_must_belong_to_same_tenant(): void
    {
        $first = $this->workspace(
            'signature-local@example.test',
            'Signature Local'
        );

        $second = $this->workspace(
            'signature-foreign@example.test',
            'Signature Foreign'
        );

        $foreignFileId =
            $this->insertFile(
                $second['tenant_id'],
                $second['user_id'],
                'DOCUMENT_SIGNATURE',
                'tenants/'
                    . $second['tenant_id']
                    . '/document-signatures/'
                    . Str::ulid()
                    . '.png'
            );

        DB::table(
            'tenant_document_settings'
        )->insert([
            'tenant_id' =>
                $first['tenant_id'],

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        $this->expectException(
            \Illuminate\Database\QueryException::class
        );

        DB::table(
            'tenant_document_settings'
        )
            ->where(
                'tenant_id',
                $first['tenant_id']
            )
            ->update([
                'signature_image_file_id' =>
                    $foreignFileId,
            ]);
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

            'user' =>
                User::query()->findOrFail(
                    $workspace['user_id']
                ),
        ];
    }

    private function insertFile(
        string $tenantId,
        string $userId,
        string $purpose,
        string $objectKey
    ): string {
        $id =
            (string) Str::ulid();

        DB::table('files')->insert([
            'id' =>
                $id,

            'tenant_id' =>
                $tenantId,

            'purpose' =>
                $purpose,

            'storage_disk' =>
                'local',

            'object_key' =>
                $objectKey,

            'original_name' =>
                'signature.png',

            'mime_type' =>
                'image/png',

            'size_bytes' =>
                1024,

            'checksum_sha256' =>
                hash(
                    'sha256',
                    $id
                ),

            'visibility' =>
                'PRIVATE',

            'uploaded_by_user_id' =>
                $userId,

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        return $id;
    }
}
