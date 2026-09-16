<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'quotations',
        'quotation_versions',
        'quotation_items',
        'quotation_status_history',
        'quotation_public_links',
        'quotation_actions',
    ];

    public function up(): void
    {
        /*
         * EXPAND
         *
         * Tambahkan business_id dahulu sebagai nullable agar
         * data existing dapat dibackfill dengan aman.
         */
        foreach (self::TABLES as $tableName) {
            Schema::table(
                $tableName,
                function (Blueprint $table): void {
                    $table->ulid('business_id')
                        ->nullable();
                }
            );
        }

        /*
         * ROOT OWNERSHIP
         *
         * Quotation existing mengikuti Business pemilik Customer.
         */
        DB::statement(
            <<<'SQL'
            UPDATE quotations AS q
            SET business_id = c.business_id
            FROM customers AS c
            WHERE c.id = q.customer_id
              AND c.tenant_id = q.tenant_id
              AND q.business_id IS NULL
            SQL
        );

        /*
         * CHILD OWNERSHIP
         *
         * Seluruh child mewarisi business_id dari aggregate parent.
         */
        DB::statement(
            <<<'SQL'
            UPDATE quotation_versions AS v
            SET business_id = q.business_id
            FROM quotations AS q
            WHERE q.id = v.quotation_id
              AND q.tenant_id = v.tenant_id
              AND v.business_id IS NULL
            SQL
        );

        DB::statement(
            <<<'SQL'
            UPDATE quotation_items AS i
            SET business_id = v.business_id
            FROM quotation_versions AS v
            WHERE v.id = i.quotation_version_id
              AND v.tenant_id = i.tenant_id
              AND i.business_id IS NULL
            SQL
        );

        DB::statement(
            <<<'SQL'
            UPDATE quotation_status_history AS h
            SET business_id = q.business_id
            FROM quotations AS q
            WHERE q.id = h.quotation_id
              AND q.tenant_id = h.tenant_id
              AND h.business_id IS NULL
            SQL
        );

        DB::statement(
            <<<'SQL'
            UPDATE quotation_public_links AS l
            SET business_id = q.business_id
            FROM quotations AS q
            WHERE q.id = l.quotation_id
              AND q.tenant_id = l.tenant_id
              AND l.business_id IS NULL
            SQL
        );

        DB::statement(
            <<<'SQL'
            UPDATE quotation_actions AS a
            SET business_id = q.business_id
            FROM quotations AS q
            WHERE q.id = a.quotation_id
              AND q.tenant_id = a.tenant_id
              AND a.business_id IS NULL
            SQL
        );

        /*
         * FAIL CLOSED
         *
         * Jangan lanjut bila ada row yang tidak berhasil
         * mendapatkan ownership Business.
         */
        foreach (self::TABLES as $tableName) {
            if (
                DB::table($tableName)
                    ->whereNull('business_id')
                    ->exists()
            ) {
                throw new RuntimeException(
                    "Business ownership backfill failed for {$tableName}."
                );
            }

            DB::statement(
                "ALTER TABLE {$tableName} "
                . 'ALTER COLUMN business_id SET NOT NULL'
            );
        }

        /*
         * SUPPORTING CANDIDATE KEYS
         *
         * customers sudah memiliki:
         * UNIQUE (id, business_id, tenant_id)
         *
         * catalog_items dan units belum.
         */
        Schema::table(
            'catalog_items',
            function (Blueprint $table): void {
                $table->unique(
                    [
                        'id',
                        'business_id',
                        'tenant_id',
                    ],
                    'catalog_items_id_business_tenant_unique'
                );
            }
        );

        Schema::table(
            'units',
            function (Blueprint $table): void {
                $table->unique(
                    [
                        'id',
                        'business_id',
                        'tenant_id',
                    ],
                    'units_id_business_tenant_unique'
                );
            }
        );

        /*
         * AGGREGATE CANDIDATE KEYS
         *
         * UNIQUE (id, tenant_id) lama sengaja dipertahankan
         * karena Invoice F9 masih mereferensikan key tersebut.
         */
        Schema::table(
            'quotations',
            function (Blueprint $table): void {
                $table->unique(
                    [
                        'id',
                        'business_id',
                        'tenant_id',
                    ],
                    'quotations_id_business_tenant_unique'
                );

                $table->foreign(
                    [
                        'business_id',
                        'tenant_id',
                    ],
                    'quotations_business_tenant_foreign'
                )
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('business_profiles');
            }
        );

        Schema::table(
            'quotation_versions',
            function (Blueprint $table): void {
                $table->unique(
                    [
                        'id',
                        'business_id',
                        'tenant_id',
                    ],
                    'quotation_versions_id_business_tenant_unique'
                );
            }
        );

        Schema::table(
            'quotation_items',
            function (Blueprint $table): void {
                $table->unique(
                    [
                        'id',
                        'business_id',
                        'tenant_id',
                    ],
                    'quotation_items_id_business_tenant_unique'
                );
            }
        );

        Schema::table(
            'quotation_public_links',
            function (Blueprint $table): void {
                $table->unique(
                    [
                        'id',
                        'business_id',
                        'tenant_id',
                    ],
                    'quotation_public_links_id_business_tenant_unique'
                );
            }
        );

        /*
         * QUOTATION -> CUSTOMER
         *
         * Customer harus berasal dari Tenant + Business yang sama.
         */
        Schema::table(
            'quotations',
            function (Blueprint $table): void {
                $table->dropForeign(
                    'quotations_customer_tenant_foreign'
                );

                $table->foreign(
                    [
                        'customer_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'quotations_customer_business_tenant_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('customers')
                    ->restrictOnDelete();
            }
        );

        /*
         * VERSION -> QUOTATION
         */
        Schema::table(
            'quotation_versions',
            function (Blueprint $table): void {
                $table->dropForeign(
                    'quotation_versions_quotation_tenant_foreign'
                );

                $table->foreign(
                    [
                        'quotation_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'quotation_versions_quotation_business_tenant_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('quotations')
                    ->cascadeOnDelete();
            }
        );

        /*
         * ITEM -> VERSION / CATALOG / UNIT
         */
        Schema::table(
            'quotation_items',
            function (Blueprint $table): void {
                $table->dropForeign(
                    'quotation_items_version_tenant_foreign'
                );

                $table->dropForeign(
                    'quotation_items_catalog_tenant_foreign'
                );

                $table->dropForeign(
                    'quotation_items_unit_tenant_foreign'
                );

                $table->foreign(
                    [
                        'quotation_version_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'quotation_items_version_business_tenant_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('quotation_versions')
                    ->cascadeOnDelete();

                $table->foreign(
                    [
                        'catalog_item_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'quotation_items_catalog_business_tenant_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('catalog_items')
                    ->restrictOnDelete();

                $table->foreign(
                    [
                        'unit_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'quotation_items_unit_business_tenant_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('units')
                    ->restrictOnDelete();
            }
        );

        /*
         * HISTORY -> QUOTATION
         */
        Schema::table(
            'quotation_status_history',
            function (Blueprint $table): void {
                $table->dropForeign(
                    'quotation_history_quotation_tenant_foreign'
                );

                $table->foreign(
                    [
                        'quotation_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'quotation_history_quotation_business_tenant_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('quotations')
                    ->cascadeOnDelete();
            }
        );

        /*
         * PUBLIC LINK -> QUOTATION + VERSION
         */
        Schema::table(
            'quotation_public_links',
            function (Blueprint $table): void {
                $table->dropForeign(
                    'quotation_public_links_quotation_tenant_foreign'
                );

                $table->dropForeign(
                    'quotation_public_links_version_tenant_foreign'
                );

                $table->foreign(
                    [
                        'quotation_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'quotation_public_links_quotation_business_tenant_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('quotations')
                    ->cascadeOnDelete();

                $table->foreign(
                    [
                        'quotation_version_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'quotation_public_links_version_business_tenant_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('quotation_versions')
                    ->cascadeOnDelete();
            }
        );

        /*
         * ACTION -> QUOTATION + VERSION + PUBLIC LINK
         */
        Schema::table(
            'quotation_actions',
            function (Blueprint $table): void {
                $table->dropForeign(
                    'quotation_actions_quotation_tenant_foreign'
                );

                $table->dropForeign(
                    'quotation_actions_version_tenant_foreign'
                );

                $table->dropForeign(
                    'quotation_actions_link_tenant_foreign'
                );

                $table->foreign(
                    [
                        'quotation_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'quotation_actions_quotation_business_tenant_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('quotations')
                    ->cascadeOnDelete();

                $table->foreign(
                    [
                        'quotation_version_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'quotation_actions_version_business_tenant_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('quotation_versions')
                    ->cascadeOnDelete();

                $table->foreign(
                    [
                        'public_link_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'quotation_actions_link_business_tenant_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('quotation_public_links')
                    ->cascadeOnDelete();
            }
        );

        /*
         * QUOTATION -> CURRENT VERSION
         *
         * current_version_id juga harus berasal dari Business
         * quotation yang sama.
         */
        Schema::table(
            'quotations',
            function (Blueprint $table): void {
                $table->dropForeign(
                    'quotations_current_version_tenant_foreign'
                );

                $table->foreign(
                    [
                        'current_version_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'quotations_current_version_business_tenant_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('quotation_versions')
                    ->restrictOnDelete();
            }
        );

        /*
         * BUSINESS-SCOPED DOCUMENT NUMBER
         */
        Schema::table(
            'quotations',
            function (Blueprint $table): void {
                $table->dropUnique(
                    'quotations_tenant_number_unique'
                );

                $table->unique(
                    [
                        'tenant_id',
                        'business_id',
                        'quotation_number',
                    ],
                    'quotations_tenant_business_number_unique'
                );

                $table->index(
                    [
                        'tenant_id',
                        'business_id',
                        'status',
                        'created_at',
                    ],
                    'quotations_tenant_business_status_created_index'
                );

                $table->index(
                    [
                        'tenant_id',
                        'business_id',
                        'customer_id',
                        'created_at',
                    ],
                    'quotations_tenant_business_customer_created_index'
                );
            }
        );
    }

    public function down(): void
    {
        /*
         * Setelah dua Business dapat memiliki nomor Penawaran
         * yang sama, rollback ke tenant-only tidak selalu aman.
         */
        $duplicateNumbers =
            DB::table('quotations')
                ->select([
                    'tenant_id',
                    'quotation_number',
                ])
                ->groupBy(
                    'tenant_id',
                    'quotation_number'
                )
                ->havingRaw(
                    'COUNT(*) > 1'
                )
                ->exists();

        if ($duplicateNumbers) {
            throw new RuntimeException(
                'Cannot rollback business-scoped quotations '
                . 'while duplicate quotation numbers exist '
                . 'inside the same tenant.'
            );
        }

        /*
         * DROP BUSINESS-SCOPED FOREIGN KEYS
         */
        Schema::table(
            'quotations',
            function (Blueprint $table): void {
                $table->dropForeign(
                    'quotations_current_version_business_tenant_foreign'
                );
            }
        );

        Schema::table(
            'quotation_actions',
            function (Blueprint $table): void {
                $table->dropForeign(
                    'quotation_actions_quotation_business_tenant_foreign'
                );

                $table->dropForeign(
                    'quotation_actions_version_business_tenant_foreign'
                );

                $table->dropForeign(
                    'quotation_actions_link_business_tenant_foreign'
                );
            }
        );

        Schema::table(
            'quotation_public_links',
            function (Blueprint $table): void {
                $table->dropForeign(
                    'quotation_public_links_quotation_business_tenant_foreign'
                );

                $table->dropForeign(
                    'quotation_public_links_version_business_tenant_foreign'
                );
            }
        );

        Schema::table(
            'quotation_status_history',
            function (Blueprint $table): void {
                $table->dropForeign(
                    'quotation_history_quotation_business_tenant_foreign'
                );
            }
        );

        Schema::table(
            'quotation_items',
            function (Blueprint $table): void {
                $table->dropForeign(
                    'quotation_items_version_business_tenant_foreign'
                );

                $table->dropForeign(
                    'quotation_items_catalog_business_tenant_foreign'
                );

                $table->dropForeign(
                    'quotation_items_unit_business_tenant_foreign'
                );
            }
        );

        Schema::table(
            'quotation_versions',
            function (Blueprint $table): void {
                $table->dropForeign(
                    'quotation_versions_quotation_business_tenant_foreign'
                );
            }
        );

        Schema::table(
            'quotations',
            function (Blueprint $table): void {
                $table->dropForeign(
                    'quotations_customer_business_tenant_foreign'
                );

                $table->dropForeign(
                    'quotations_business_tenant_foreign'
                );
            }
        );

        /*
         * RESTORE LEGACY TENANT-SCOPED FOREIGN KEYS
         */
        Schema::table(
            'quotations',
            function (Blueprint $table): void {
                $table->foreign(
                    [
                        'customer_id',
                        'tenant_id',
                    ],
                    'quotations_customer_tenant_foreign'
                )
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('customers')
                    ->restrictOnDelete();
            }
        );

        Schema::table(
            'quotation_versions',
            function (Blueprint $table): void {
                $table->foreign(
                    [
                        'quotation_id',
                        'tenant_id',
                    ],
                    'quotation_versions_quotation_tenant_foreign'
                )
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('quotations')
                    ->cascadeOnDelete();
            }
        );

        Schema::table(
            'quotation_items',
            function (Blueprint $table): void {
                $table->foreign(
                    [
                        'quotation_version_id',
                        'tenant_id',
                    ],
                    'quotation_items_version_tenant_foreign'
                )
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('quotation_versions')
                    ->cascadeOnDelete();

                $table->foreign(
                    [
                        'catalog_item_id',
                        'tenant_id',
                    ],
                    'quotation_items_catalog_tenant_foreign'
                )
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('catalog_items')
                    ->restrictOnDelete();

                $table->foreign(
                    [
                        'unit_id',
                        'tenant_id',
                    ],
                    'quotation_items_unit_tenant_foreign'
                )
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('units')
                    ->restrictOnDelete();
            }
        );

        Schema::table(
            'quotation_status_history',
            function (Blueprint $table): void {
                $table->foreign(
                    [
                        'quotation_id',
                        'tenant_id',
                    ],
                    'quotation_history_quotation_tenant_foreign'
                )
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('quotations')
                    ->cascadeOnDelete();
            }
        );

        Schema::table(
            'quotation_public_links',
            function (Blueprint $table): void {
                $table->foreign(
                    [
                        'quotation_id',
                        'tenant_id',
                    ],
                    'quotation_public_links_quotation_tenant_foreign'
                )
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('quotations')
                    ->cascadeOnDelete();

                $table->foreign(
                    [
                        'quotation_version_id',
                        'tenant_id',
                    ],
                    'quotation_public_links_version_tenant_foreign'
                )
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('quotation_versions')
                    ->cascadeOnDelete();
            }
        );

        Schema::table(
            'quotation_actions',
            function (Blueprint $table): void {
                $table->foreign(
                    [
                        'quotation_id',
                        'tenant_id',
                    ],
                    'quotation_actions_quotation_tenant_foreign'
                )
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('quotations')
                    ->cascadeOnDelete();

                $table->foreign(
                    [
                        'quotation_version_id',
                        'tenant_id',
                    ],
                    'quotation_actions_version_tenant_foreign'
                )
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('quotation_versions')
                    ->cascadeOnDelete();

                $table->foreign(
                    [
                        'public_link_id',
                        'tenant_id',
                    ],
                    'quotation_actions_link_tenant_foreign'
                )
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('quotation_public_links')
                    ->cascadeOnDelete();
            }
        );

        Schema::table(
            'quotations',
            function (Blueprint $table): void {
                $table->foreign(
                    [
                        'current_version_id',
                        'tenant_id',
                    ],
                    'quotations_current_version_tenant_foreign'
                )
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('quotation_versions')
                    ->restrictOnDelete();
            }
        );

        /*
         * RESTORE TENANT-LEVEL NUMBER UNIQUENESS
         */
        Schema::table(
            'quotations',
            function (Blueprint $table): void {
                $table->dropUnique(
                    'quotations_tenant_business_number_unique'
                );

                $table->unique(
                    [
                        'tenant_id',
                        'quotation_number',
                    ],
                    'quotations_tenant_number_unique'
                );

                $table->dropIndex(
                    'quotations_tenant_business_status_created_index'
                );

                $table->dropIndex(
                    'quotations_tenant_business_customer_created_index'
                );
            }
        );

        /*
         * DROP BUSINESS CANDIDATE KEYS
         */
        Schema::table(
            'quotation_public_links',
            function (Blueprint $table): void {
                $table->dropUnique(
                    'quotation_public_links_id_business_tenant_unique'
                );
            }
        );

        Schema::table(
            'quotation_items',
            function (Blueprint $table): void {
                $table->dropUnique(
                    'quotation_items_id_business_tenant_unique'
                );
            }
        );

        Schema::table(
            'quotation_versions',
            function (Blueprint $table): void {
                $table->dropUnique(
                    'quotation_versions_id_business_tenant_unique'
                );
            }
        );

        Schema::table(
            'quotations',
            function (Blueprint $table): void {
                $table->dropUnique(
                    'quotations_id_business_tenant_unique'
                );
            }
        );

        Schema::table(
            'units',
            function (Blueprint $table): void {
                $table->dropUnique(
                    'units_id_business_tenant_unique'
                );
            }
        );

        Schema::table(
            'catalog_items',
            function (Blueprint $table): void {
                $table->dropUnique(
                    'catalog_items_id_business_tenant_unique'
                );
            }
        );

        /*
         * CONTRACT
         */
        foreach (array_reverse(self::TABLES) as $tableName) {
            Schema::table(
                $tableName,
                function (Blueprint $table): void {
                    $table->dropColumn(
                        'business_id'
                    );
                }
            );
        }
    }
};
