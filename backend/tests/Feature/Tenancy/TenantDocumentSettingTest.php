<?php

namespace Tests\Feature\Tenancy;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use App\Models\TenantDocumentSetting;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantDocumentSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_tenant_can_have_one_document_setting_record(): void
    {
        $workspace = $this->workspace(
            'document-setting@example.test',
            'Reklame Nusantara'
        );

        TenantDocumentSetting::query()->create([
            'tenant_id' =>
                $workspace['tenant_id'],
            'business_name' =>
                'PT Reklame Nusantara',
            'address' =>
                'Jl. Contoh No. 10',
            'phone' =>
                '081234567890',
            'email' =>
                'halo@example.test',
            'tax_id' =>
                '01.234.567.8-999.000',
            'quotation_footer' =>
                'Terima kasih atas kepercayaan Anda.',
        ]);

        $this->assertDatabaseHas(
            'tenant_document_settings',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],
                'business_name' =>
                    'PT Reklame Nusantara',
            ]
        );
    }

    public function test_database_rejects_second_setting_for_same_tenant(): void
    {
        $workspace = $this->workspace(
            'document-setting-unique@example.test',
            'Unique Document Setting'
        );

        DB::table(
            'tenant_document_settings'
        )->insert([
            'tenant_id' =>
                $workspace['tenant_id'],
            'business_name' =>
                'Pertama',
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
        )->insert([
            'tenant_id' =>
                $workspace['tenant_id'],
            'business_name' =>
                'Kedua',
            'created_at' =>
                now(),
            'updated_at' =>
                now(),
        ]);
    }

    public function test_setting_is_removed_when_tenant_is_deleted(): void
    {
        $workspace = $this->workspace(
            'document-setting-cascade@example.test',
            'Cascade Document Setting'
        );

        DB::table(
            'tenant_document_settings'
        )->insert([
            'tenant_id' =>
                $workspace['tenant_id'],
            'business_name' =>
                'Cascade Test',
            'created_at' =>
                now(),
            'updated_at' =>
                now(),
        ]);

        DB::table('tenants')
            ->where(
                'id',
                $workspace['tenant_id']
            )
            ->delete();

        $this->assertDatabaseMissing(
            'tenant_document_settings',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],
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
                'Document Setting Test',
            'email' =>
                $email,
            'password' =>
                'SecurePassword123!',
            'tenant_name' =>
                $tenantName,
        ]);
    }
}
