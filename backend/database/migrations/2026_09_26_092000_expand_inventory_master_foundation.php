<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * =========================================================
         * Inventory Categories
         * =========================================================
         */

        Schema::create(
            'inventory_categories',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->ulid('tenant_id');
                $table->ulid('business_id');

                $table->string(
                    'code',
                    80
                );

                $table->string(
                    'name',
                    120
                );

                $table->text(
                    'description'
                )->nullable();

                $table->string(
                    'status',
                    32
                )->default('ACTIVE');

                $table->timestampsTz();

                $table->unique(
                    [
                        'id',
                        'business_id',
                        'tenant_id',
                    ],
                    'inventory_categories_id_business_tenant_unique'
                );

                $table->unique(
                    [
                        'tenant_id',
                        'business_id',
                        'code',
                    ],
                    'inventory_categories_business_code_unique'
                );

                $table->foreign(
                    'tenant_id'
                )
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();

                $table->foreign(
                    [
                        'business_id',
                        'tenant_id',
                    ],
                    'inventory_categories_business_tenant_foreign'
                )
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('business_profiles')
                    ->cascadeOnDelete();

                $table->index(
                    [
                        'tenant_id',
                        'business_id',
                        'status',
                        'name',
                    ],
                    'inventory_categories_context_status_name_idx'
                );
            }
        );

        DB::statement(
            "ALTER TABLE inventory_categories
             ADD CONSTRAINT inventory_categories_status_check
             CHECK (
                status IN (
                    'ACTIVE',
                    'INACTIVE'
                )
             )"
        );

        /*
         * =========================================================
         * Materials — Inventory Master v2 foundation
         * =========================================================
         */

        Schema::table(
            'materials',
            function (Blueprint $table): void {
                $table->ulid(
                    'category_id'
                )->nullable();

                $table->string(
                    'stock_tracking',
                    32
                )->default('TRACKED');

                $table->decimal(
                    'minimum_stock',
                    18,
                    4
                )->nullable();

                $table->decimal(
                    'reorder_point',
                    18,
                    4
                )->nullable();

                $table->decimal(
                    'maximum_stock',
                    18,
                    4
                )->nullable();

                $table->text(
                    'description'
                )->nullable();

                $table->index(
                    [
                        'tenant_id',
                        'business_id',
                        'stock_tracking',
                        'status',
                    ],
                    'materials_context_stock_tracking_idx'
                );
            }
        );

        DB::statement(
            "ALTER TABLE materials
             ADD CONSTRAINT materials_stock_tracking_check
             CHECK (
                stock_tracking IN (
                    'TRACKED',
                    'NOT_TRACKED'
                )
             )"
        );

        DB::statement(
            "ALTER TABLE materials
             ADD CONSTRAINT materials_stock_threshold_check
             CHECK (
                (minimum_stock IS NULL OR minimum_stock >= 0)
                AND
                (reorder_point IS NULL OR reorder_point >= 0)
                AND
                (maximum_stock IS NULL OR maximum_stock >= 0)
                AND
                (
                    minimum_stock IS NULL
                    OR reorder_point IS NULL
                    OR minimum_stock <= reorder_point
                )
                AND
                (
                    reorder_point IS NULL
                    OR maximum_stock IS NULL
                    OR reorder_point <= maximum_stock
                )
                AND
                (
                    minimum_stock IS NULL
                    OR maximum_stock IS NULL
                    OR minimum_stock <= maximum_stock
                )
                AND
                (
                    stock_tracking = 'TRACKED'
                    OR (
                        minimum_stock IS NULL
                        AND reorder_point IS NULL
                        AND maximum_stock IS NULL
                    )
                )
             )"
        );

        /*
         * =========================================================
         * Backfill legacy materials.category → category_id
         *
         * Legacy category column sengaja dipertahankan untuk
         * compatibility API v1. Tidak menjadi source of truth baru.
         * =========================================================
         */

        $legacyCategories =
            DB::table('materials')
                ->select([
                    'tenant_id',
                    'business_id',
                    'category',
                ])
                ->whereNotNull(
                    'category'
                )
                ->whereRaw(
                    "BTRIM(category) <> ''"
                )
                ->distinct()
                ->orderBy('tenant_id')
                ->orderBy('business_id')
                ->orderBy('category')
                ->get();

        foreach ($legacyCategories as $legacy) {
            $name =
                trim(
                    (string) $legacy->category
                );

            $existing =
                DB::table(
                    'inventory_categories'
                )
                    ->where(
                        'tenant_id',
                        $legacy->tenant_id
                    )
                    ->where(
                        'business_id',
                        $legacy->business_id
                    )
                    ->whereRaw(
                        'LOWER(BTRIM(name)) = LOWER(BTRIM(?))',
                        [$name]
                    )
                    ->first();

            if ($existing) {
                $categoryId =
                    (string) $existing->id;
            } else {
                $baseCode =
                    Str::upper(
                        Str::slug(
                            $name,
                            '_'
                        )
                    );

                if ($baseCode === '') {
                    $baseCode =
                        'CATEGORY';
                }

                $baseCode =
                    substr(
                        $baseCode,
                        0,
                        68
                    );

                $code =
                    $baseCode;

                $sequence =
                    1;

                while (
                    DB::table(
                        'inventory_categories'
                    )
                        ->where(
                            'tenant_id',
                            $legacy->tenant_id
                        )
                        ->where(
                            'business_id',
                            $legacy->business_id
                        )
                        ->where(
                            'code',
                            $code
                        )
                        ->exists()
                ) {
                    $suffix =
                        '_'.$sequence;

                    $code =
                        substr(
                            $baseCode,
                            0,
                            80 - strlen($suffix)
                        )
                        .$suffix;

                    $sequence++;
                }

                $categoryId =
                    (string) Str::ulid();

                DB::table(
                    'inventory_categories'
                )->insert([
                    'id' =>
                        $categoryId,

                    'tenant_id' =>
                        $legacy->tenant_id,

                    'business_id' =>
                        $legacy->business_id,

                    'code' =>
                        $code,

                    'name' =>
                        $name,

                    'description' =>
                        null,

                    'status' =>
                        'ACTIVE',

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);
            }

            DB::table(
                'materials'
            )
                ->where(
                    'tenant_id',
                    $legacy->tenant_id
                )
                ->where(
                    'business_id',
                    $legacy->business_id
                )
                ->where(
                    'category',
                    $legacy->category
                )
                ->update([
                    'category_id' =>
                        $categoryId,
                ]);
        }

        /*
         * Duplicate guard:
         * kategori dengan nama sama (case/whitespace insensitive)
         * tidak boleh ganda dalam business context yang sama.
         */

        DB::statement(
            "CREATE UNIQUE INDEX
                inventory_categories_business_name_ci_unique
             ON inventory_categories (
                tenant_id,
                business_id,
                LOWER(BTRIM(name))
             )"
        );

        /*
         * Composite FK dipasang setelah backfill.
         * RESTRICT dipakai agar category historis/master yang masih
         * direferensikan tidak dapat dihapus begitu saja.
         */

        Schema::table(
            'materials',
            function (Blueprint $table): void {
                $table->foreign(
                    [
                        'category_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'materials_inventory_category_business_tenant_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on(
                        'inventory_categories'
                    )
                    ->restrictOnDelete();
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'materials',
            function (Blueprint $table): void {
                $table->dropForeign(
                    'materials_inventory_category_business_tenant_foreign'
                );
            }
        );

        DB::statement(
            "ALTER TABLE materials
             DROP CONSTRAINT IF EXISTS
             materials_stock_threshold_check"
        );

        DB::statement(
            "ALTER TABLE materials
             DROP CONSTRAINT IF EXISTS
             materials_stock_tracking_check"
        );

        Schema::table(
            'materials',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'materials_context_stock_tracking_idx'
                );

                $table->dropColumn([
                    'category_id',
                    'stock_tracking',
                    'minimum_stock',
                    'reorder_point',
                    'maximum_stock',
                    'description',
                ]);
            }
        );

        Schema::dropIfExists(
            'inventory_categories'
        );
    }
};
