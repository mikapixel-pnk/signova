<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            /*
             * ---------------------------------------------------------
             * EXPAND: nullable business_id first.
             * ---------------------------------------------------------
             */
            Schema::table(
                'customers',
                function (Blueprint $table): void {
                    $table->ulid('business_id')
                        ->nullable()
                        ->after('tenant_id');
                }
            );

            Schema::table(
                'customer_contacts',
                function (Blueprint $table): void {
                    $table->ulid('business_id')
                        ->nullable()
                        ->after('tenant_id');
                }
            );

            Schema::table(
                'customer_addresses',
                function (Blueprint $table): void {
                    $table->ulid('business_id')
                        ->nullable()
                        ->after('tenant_id');
                }
            );

            /*
             * ---------------------------------------------------------
             * Legacy customer data belongs to the default Business
             * of its Tenant.
             * ---------------------------------------------------------
             */
            DB::statement(<<<'SQL'
                UPDATE customers AS c
                SET business_id = bp.id
                FROM business_profiles AS bp
                WHERE c.business_id IS NULL
                  AND bp.tenant_id = c.tenant_id
                  AND bp.is_default = TRUE
                  AND bp.status = 'ACTIVE'
            SQL);

            $missingCustomerBusiness =
                DB::table('customers')
                    ->whereNull('business_id')
                    ->count();

            if ($missingCustomerBusiness > 0) {
                throw new \RuntimeException(
                    'Customer business backfill failed: '
                    . $missingCustomerBusiness
                    . ' rows remain without business_id.'
                );
            }

            /*
             * Child rows inherit Business from their Customer.
             */
            DB::statement(<<<'SQL'
                UPDATE customer_contacts AS cc
                SET business_id = c.business_id
                FROM customers AS c
                WHERE cc.business_id IS NULL
                  AND cc.customer_id = c.id
                  AND cc.tenant_id = c.tenant_id
            SQL);

            DB::statement(<<<'SQL'
                UPDATE customer_addresses AS ca
                SET business_id = c.business_id
                FROM customers AS c
                WHERE ca.business_id IS NULL
                  AND ca.customer_id = c.id
                  AND ca.tenant_id = c.tenant_id
            SQL);

            $missingContactBusiness =
                DB::table('customer_contacts')
                    ->whereNull('business_id')
                    ->count();

            $missingAddressBusiness =
                DB::table('customer_addresses')
                    ->whereNull('business_id')
                    ->count();

            if (
                $missingContactBusiness > 0
                || $missingAddressBusiness > 0
            ) {
                throw new \RuntimeException(
                    'Customer child business backfill failed. '
                    . "contacts={$missingContactBusiness}, "
                    . "addresses={$missingAddressBusiness}."
                );
            }

            /*
             * ---------------------------------------------------------
             * Customer Business compatibility keys.
             * Keep old tenant-only keys during EXPAND.
             * ---------------------------------------------------------
             */
            Schema::table(
                'customers',
                function (Blueprint $table): void {
                    $table->foreign(
                        ['business_id', 'tenant_id'],
                        'customers_business_tenant_foreign'
                    )
                        ->references(['id', 'tenant_id'])
                        ->on('business_profiles');

                    $table->unique(
                        [
                            'id',
                            'business_id',
                            'tenant_id',
                        ],
                        'customers_id_business_tenant_unique'
                    );

                    $table->unique(
                        [
                            'tenant_id',
                            'business_id',
                            'code',
                        ],
                        'customers_tenant_business_code_unique'
                    );

                    $table->index(
                        [
                            'tenant_id',
                            'business_id',
                            'status',
                            'name',
                        ],
                        'customers_tenant_business_status_name_index'
                    );
                }
            );

            /*
             * ---------------------------------------------------------
             * Child Business ownership + same-Business Customer FK.
             * Existing tenant-only FK remains during EXPAND.
             * ---------------------------------------------------------
             */
            Schema::table(
                'customer_contacts',
                function (Blueprint $table): void {
                    $table->foreign(
                        ['business_id', 'tenant_id'],
                        'customer_contacts_business_tenant_foreign'
                    )
                        ->references(['id', 'tenant_id'])
                        ->on('business_profiles');

                    $table->foreign(
                        [
                            'customer_id',
                            'business_id',
                            'tenant_id',
                        ],
                        'customer_contacts_customer_business_tenant_foreign'
                    )
                        ->references([
                            'id',
                            'business_id',
                            'tenant_id',
                        ])
                        ->on('customers')
                        ->cascadeOnDelete();

                    $table->index(
                        [
                            'tenant_id',
                            'business_id',
                            'customer_id',
                            'status',
                        ],
                        'customer_contacts_tenant_business_customer_status_index'
                    );
                }
            );

            Schema::table(
                'customer_addresses',
                function (Blueprint $table): void {
                    $table->foreign(
                        ['business_id', 'tenant_id'],
                        'customer_addresses_business_tenant_foreign'
                    )
                        ->references(['id', 'tenant_id'])
                        ->on('business_profiles');

                    $table->foreign(
                        [
                            'customer_id',
                            'business_id',
                            'tenant_id',
                        ],
                        'customer_addresses_customer_business_tenant_foreign'
                    )
                        ->references([
                            'id',
                            'business_id',
                            'tenant_id',
                        ])
                        ->on('customers')
                        ->cascadeOnDelete();

                    $table->index(
                        [
                            'tenant_id',
                            'business_id',
                            'customer_id',
                            'status',
                        ],
                        'customer_addresses_tenant_business_customer_status_index'
                    );
                }
            );
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            Schema::table(
                'customer_addresses',
                function (Blueprint $table): void {
                    $table->dropForeign(
                        'customer_addresses_customer_business_tenant_foreign'
                    );

                    $table->dropForeign(
                        'customer_addresses_business_tenant_foreign'
                    );

                    $table->dropIndex(
                        'customer_addresses_tenant_business_customer_status_index'
                    );

                    $table->dropColumn('business_id');
                }
            );

            Schema::table(
                'customer_contacts',
                function (Blueprint $table): void {
                    $table->dropForeign(
                        'customer_contacts_customer_business_tenant_foreign'
                    );

                    $table->dropForeign(
                        'customer_contacts_business_tenant_foreign'
                    );

                    $table->dropIndex(
                        'customer_contacts_tenant_business_customer_status_index'
                    );

                    $table->dropColumn('business_id');
                }
            );

            Schema::table(
                'customers',
                function (Blueprint $table): void {
                    $table->dropForeign(
                        'customers_business_tenant_foreign'
                    );

                    $table->dropUnique(
                        'customers_id_business_tenant_unique'
                    );

                    $table->dropUnique(
                        'customers_tenant_business_code_unique'
                    );

                    $table->dropIndex(
                        'customers_tenant_business_status_name_index'
                    );

                    $table->dropColumn('business_id');
                }
            );
        });
    }
};
