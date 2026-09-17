<?php

namespace Tests\Feature\Api;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use App\Models\User;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InvoiceTemplateSettingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_get_returns_virtual_default_without_creating_setting_row(): void
    {
        $workspace =
            $this->workspace(
                'template-default@example.test',
                'Template Default'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->assertDatabaseMissing(
            'tenant_document_settings',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],
            ]
        );

        $this->getJson(
            '/api/v1/settings/invoice-templates'
        )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.selected.template_key',
                'classic_blue'
            )
            ->assertJsonPath(
                'data.selected.palette_key',
                'blue'
            );

        $this->assertDatabaseMissing(
            'tenant_document_settings',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],
            ]
        );
    }

    public function test_catalog_contains_starter_and_premium_templates(): void
    {
        $workspace =
            $this->workspace(
                'template-catalog@example.test',
                'Template Catalog'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $response =
            $this->getJson(
                '/api/v1/settings/invoice-templates'
            )
                ->assertOk();

        $templates =
            $response->json(
                'data.templates'
            );

        $this->assertCount(
            5,
            $templates
        );

        $this->assertSame(
            [
                'classic_blue',
                'modern_emerald',
                'minimal_slate',
                'ocean_blue',
                'premium_navy',
            ],
            array_column(
                $templates,
                'key'
            )
        );

        foreach (
            array_slice(
                $templates,
                0,
                4
            ) as $template
        ) {
            $this->assertSame(
                'STARTER',
                $template['tier']
            );

            $this->assertTrue(
                $template['is_available']
            );
        }

        $ocean =
            $templates[3];

        $this->assertSame(
            'Ocean Blue',
            $ocean['name']
        );

        $this->assertSame(
            'STARTER',
            $ocean['tier']
        );

        $this->assertTrue(
            $ocean['is_available']
        );

        $this->assertSame(
            'ocean',
            $ocean['default_palette']
        );

        $this->assertArrayNotHasKey(
            'author',
            $ocean
        );

        $premium =
            $templates[4];

        $this->assertSame(
            'Premium Navy',
            $premium['name']
        );

        $this->assertSame(
            'PREMIUM',
            $premium['tier']
        );

        $this->assertFalse(
            $premium['is_available']
        );

        $this->assertArrayNotHasKey(
            'author',
            $premium
        );
    }

    public function test_owner_can_select_template_and_palette(): void
    {
        $workspace =
            $this->workspace(
                'template-update@example.test',
                'Template Update'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->patchJson(
            '/api/v1/settings/invoice-templates',
            [
                'invoice_template_key' =>
                    'modern_emerald',

                'invoice_palette_key' =>
                    'emerald',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.selected.template_key',
                'modern_emerald'
            )
            ->assertJsonPath(
                'data.selected.palette_key',
                'emerald'
            );

        $this->assertDatabaseHas(
            'tenant_document_settings',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],

                'invoice_template_key' =>
                    'modern_emerald',

                'invoice_palette_key' =>
                    'emerald',
            ]
        );
    }

    public function test_owner_can_select_ocean_blue_starter_template(): void
    {
        $workspace =
            $this->workspace(
                'template-ocean@example.test',
                'Template Ocean'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->patchJson(
            '/api/v1/settings/invoice-templates',
            [
                'invoice_template_key' =>
                    'ocean_blue',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.selected.template_key',
                'ocean_blue'
            )
            ->assertJsonPath(
                'data.selected.palette_key',
                'ocean'
            );

        $this->assertDatabaseHas(
            'tenant_document_settings',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],

                'invoice_template_key' =>
                    'ocean_blue',

                'invoice_palette_key' =>
                    'ocean',
            ]
        );
    }

    public function test_palette_is_optional_and_uses_template_default(): void
    {
        $workspace =
            $this->workspace(
                'template-default-palette@example.test',
                'Template Default Palette'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->patchJson(
            '/api/v1/settings/invoice-templates',
            [
                'invoice_template_key' =>
                    'minimal_slate',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.selected.template_key',
                'minimal_slate'
            )
            ->assertJsonPath(
                'data.selected.palette_key',
                'slate'
            );

        $this->assertDatabaseHas(
            'tenant_document_settings',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],

                'invoice_template_key' =>
                    'minimal_slate',

                'invoice_palette_key' =>
                    'slate',
            ]
        );
    }

    public function test_unsupported_palette_is_rejected(): void
    {
        $workspace =
            $this->workspace(
                'template-invalid-palette@example.test',
                'Template Invalid Palette'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->patchJson(
            '/api/v1/settings/invoice-templates',
            [
                'invoice_template_key' =>
                    'classic_blue',

                'invoice_palette_key' =>
                    'emerald',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );

        $this->assertDatabaseMissing(
            'tenant_document_settings',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],
            ]
        );
    }

    public function test_unknown_template_is_rejected(): void
    {
        $workspace =
            $this->workspace(
                'template-invalid@example.test',
                'Template Invalid'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->patchJson(
            '/api/v1/settings/invoice-templates',
            [
                'invoice_template_key' =>
                    'not_a_template',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );
    }

    public function test_settings_view_capability_is_required_for_catalog(): void
    {
        $workspace =
            $this->workspace(
                'template-view-denied@example.test',
                'Template View Denied'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->denyCapability(
            $workspace,
            'settings.view'
        );

        $this->getJson(
            '/api/v1/settings/invoice-templates'
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );
    }

    public function test_settings_manage_capability_is_required_for_update(): void
    {
        $workspace =
            $this->workspace(
                'template-manage-denied@example.test',
                'Template Manage Denied'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->denyCapability(
            $workspace,
            'settings.manage'
        );

        $this->patchJson(
            '/api/v1/settings/invoice-templates',
            [
                'invoice_template_key' =>
                    'modern_emerald',
            ]
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );
    }

    public function test_template_selection_is_tenant_scoped(): void
    {
        $first =
            $this->workspace(
                'template-first@example.test',
                'Template First'
            );

        $second =
            $this->workspace(
                'template-second@example.test',
                'Template Second'
            );

        $this->actingAsWorkspace(
            $first
        );

        $this->patchJson(
            '/api/v1/settings/invoice-templates',
            [
                'invoice_template_key' =>
                    'modern_emerald',
            ]
        )->assertOk();

        $this->actingAsWorkspace(
            $second
        );

        $this->patchJson(
            '/api/v1/settings/invoice-templates',
            [
                'invoice_template_key' =>
                    'minimal_slate',
            ]
        )->assertOk();

        $this->getJson(
            '/api/v1/settings/invoice-templates'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.selected.template_key',
                'minimal_slate'
            )
            ->assertJsonPath(
                'data.selected.palette_key',
                'slate'
            );

        $this->actingAsWorkspace(
            $first
        );

        $this->getJson(
            '/api/v1/settings/invoice-templates'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.selected.template_key',
                'modern_emerald'
            )
            ->assertJsonPath(
                'data.selected.palette_key',
                'emerald'
            );

        $this->assertDatabaseHas(
            'tenant_document_settings',
            [
                'tenant_id' =>
                    $first['tenant_id'],

                'invoice_template_key' =>
                    'modern_emerald',
            ]
        );

        $this->assertDatabaseHas(
            'tenant_document_settings',
            [
                'tenant_id' =>
                    $second['tenant_id'],

                'invoice_template_key' =>
                    'minimal_slate',
            ]
        );
    }

    public function test_owner_can_preview_all_catalog_templates(): void
    {
        $workspace =
            $this->workspace(
                'template-preview@example.test',
                'Template Preview'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $cases = [
            'classic_blue' =>
                'classic',

            'modern_emerald' =>
                'modern',

            'minimal_slate' =>
                'minimal',

            'ocean_blue' =>
                'ocean',

            'premium_navy' =>
                'premium',
        ];

        foreach (
            $cases
            as $templateKey =>
                $layout
        ) {
            $response =
                $this->get(
                    "/api/v1/settings/invoice-templates/{$templateKey}/preview"
                )
                    ->assertOk()
                    ->assertHeader(
                        'Content-Type',
                        'text/html; charset=UTF-8'
                    );

            $this->assertStringContainsString(
                'data-invoice-layout="'
                . $layout
                . '"',
                $response->getContent()
            );

            $this->assertStringContainsString(
                'INV-202609-0001',
                $response->getContent()
            );
        }
    }

    public function test_preview_uses_active_business_branding(): void
    {
        $workspace =
            $this->workspace(
                'template-branding@example.test',
                'Branding Preview'
            );

        $businessId =
            DB::table(
                'business_profiles'
            )
                ->where(
                    'tenant_id',
                    $workspace['tenant_id']
                )
                ->value('id');

        $this->assertNotNull(
            $businessId
        );

        DB::table(
            'business_profiles'
        )
            ->where(
                'tenant_id',
                $workspace['tenant_id']
            )
            ->where(
                'id',
                $businessId
            )
            ->update([
                'name' =>
                    'CV Signova Visual',

                'address' =>
                    'Jl. Branding No. 17',

                'phone' =>
                    '081234567890',

                'email' =>
                    'halo@signova.test',

                'tax_id' =>
                    '12.345.678.9-012.345',

                'updated_at' =>
                    now(),
            ]);

        DB::table(
            'tenant_document_settings'
        )->insert([
            'id' =>
                (string) str()->ulid(),

            'tenant_id' =>
                $workspace['tenant_id'],

            'business_id' =>
                $businessId,

            'invoice_footnote' =>
                'Catatan pembayaran usaha aktif.',

            'signature_name' =>
                'Novel',

            'signature_title' =>
                'Pemilik',

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        $this->actingAsWorkspace(
            $workspace
        );

        $response =
            $this->get(
                '/api/v1/settings/invoice-templates/ocean_blue/preview'
            )
                ->assertOk();

        $html =
            $response->getContent();

        $this->assertStringContainsString(
            'CV Signova Visual',
            $html
        );

        $this->assertStringContainsString(
            'Jl. Branding No. 17',
            $html
        );

        $this->assertStringContainsString(
            'halo@signova.test',
            $html
        );

        $this->assertStringContainsString(
            'Catatan pembayaran usaha aktif.',
            $html
        );

        $this->assertStringContainsString(
            'Novel',
            $html
        );

        $this->assertStringNotContainsString(
            'PT Contoh Reklame',
            $html
        );
    }

    public function test_preview_uses_active_business_logo_and_signature(): void
    {
        \Illuminate\Support\Facades\Storage::fake(
            'local'
        );

        config()->set(
            'filesystems.private_disk',
            'local'
        );

        $workspace =
            $this->workspace(
                'template-branding-image@example.test',
                'Branding Image Preview'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->post(
            '/api/v1/settings/business-profile/logo',
            [
                'logo' =>
                    \Illuminate\Http\UploadedFile::fake()
                        ->image(
                            'logo-preview.png',
                            800,
                            320
                        ),
            ]
        )->assertOk();

        $this->post(
            '/api/v1/settings/document/signature',
            [
                'signature' =>
                    \Illuminate\Http\UploadedFile::fake()
                        ->image(
                            'signature-preview.png',
                            500,
                            180
                        ),
            ]
        )->assertOk();

        $logoFile =
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

        $signatureFile =
            DB::table('files')
                ->where(
                    'tenant_id',
                    $workspace['tenant_id']
                )
                ->where(
                    'purpose',
                    'DOCUMENT_SIGNATURE'
                )
                ->first();

        $this->assertNotNull(
            $logoFile
        );

        $this->assertNotNull(
            $signatureFile
        );

        $logoContents =
            \Illuminate\Support\Facades\Storage::disk(
                'local'
            )->get(
                $logoFile->object_key
            );

        $signatureContents =
            \Illuminate\Support\Facades\Storage::disk(
                'local'
            )->get(
                $signatureFile->object_key
            );

        $response =
            $this->get(
                '/api/v1/settings/invoice-templates/ocean_blue/preview'
            )
                ->assertOk();

        $html =
            $response->getContent();

        $this->assertStringContainsString(
            'data:image/png;base64,'
            . base64_encode(
                $logoContents
            ),
            $html
        );

        $this->assertStringContainsString(
            'data:image/png;base64,'
            . base64_encode(
                $signatureContents
            ),
            $html
        );

        $this->assertStringContainsString(
            'alt="Logo usaha"',
            $html
        );

        $this->assertStringContainsString(
            'alt="Tanda tangan"',
            $html
        );
    }

    public function test_preview_does_not_create_document_setting_row(): void
    {
        $workspace =
            $this->workspace(
                'template-preview-readonly@example.test',
                'Template Preview Readonly'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->get(
            '/api/v1/settings/invoice-templates/classic_blue/preview'
        )->assertOk();

        $this->assertDatabaseMissing(
            'tenant_document_settings',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],
            ]
        );
    }

    public function test_preview_unknown_template_returns_not_found(): void
    {
        $workspace =
            $this->workspace(
                'template-preview-missing@example.test',
                'Template Preview Missing'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->get(
            '/api/v1/settings/invoice-templates/not_a_template/preview'
        )->assertNotFound();
    }

    public function test_preview_rejects_unsupported_palette(): void
    {
        $workspace =
            $this->workspace(
                'template-preview-palette@example.test',
                'Template Preview Palette'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->getJson(
            '/api/v1/settings/invoice-templates/classic_blue/preview?palette=emerald'
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );
    }

    public function test_settings_view_capability_is_required_for_preview(): void
    {
        $workspace =
            $this->workspace(
                'template-preview-denied@example.test',
                'Template Preview Denied'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->denyCapability(
            $workspace,
            'settings.view'
        );

        $this->get(
            '/api/v1/settings/invoice-templates/classic_blue/preview'
        )
            ->assertForbidden();
    }


    public function test_premium_template_is_visible_but_not_selectable_on_starter(): void
    {
        $workspace =
            $this->workspace(
                'template-premium-locked@example.test',
                'Template Premium Locked'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->getJson(
            '/api/v1/settings/invoice-templates'
        )
            ->assertOk()
            ->assertJsonFragment([
                'key' => 'premium_navy',
                'name' => 'Premium Navy',
                'tier' => 'PREMIUM',
                'is_available' => false,
            ]);

        $this->patchJson(
            '/api/v1/settings/invoice-templates',
            [
                'invoice_template_key' =>
                    'premium_navy',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );
    }


    private function workspace(
        string $email,
        string $businessName
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
