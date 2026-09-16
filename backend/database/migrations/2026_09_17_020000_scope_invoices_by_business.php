<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * =========================================================
         * 1. EXPAND
         * =========================================================
         */

        DB::statement(
            <<<'SQL'
            ALTER TABLE invoices
            ADD COLUMN business_id CHAR(26)
            SQL
        );

        DB::statement(
            <<<'SQL'
            ALTER TABLE invoice_items
            ADD COLUMN business_id CHAR(26)
            SQL
        );

        DB::statement(
            <<<'SQL'
            ALTER TABLE invoice_status_history
            ADD COLUMN business_id CHAR(26)
            SQL
        );

        /*
         * =========================================================
         * 2. BACKFILL ROOT FROM CUSTOMER
         * =========================================================
         */

        DB::statement(
            <<<'SQL'
            UPDATE invoices AS i
            SET business_id = c.business_id
            FROM customers AS c
            WHERE c.id = i.customer_id
              AND c.tenant_id = i.tenant_id
              AND i.business_id IS NULL
            SQL
        );

        /*
         * Converted invoices must agree with source quotation.
         */
        DB::statement(
            <<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (
                    SELECT 1
                    FROM invoices AS i
                    JOIN quotations AS q
                      ON q.id = i.source_quotation_id
                     AND q.tenant_id = i.tenant_id
                    WHERE i.source_quotation_id IS NOT NULL
                      AND i.business_id IS DISTINCT FROM q.business_id
                ) THEN
                    RAISE EXCEPTION
                        'Invoice business does not match source quotation business';
                END IF;
            END
            $$
            SQL
        );

        DB::statement(
            <<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (
                    SELECT 1
                    FROM invoices AS i
                    JOIN quotation_versions AS qv
                      ON qv.id = i.source_quotation_version_id
                     AND qv.tenant_id = i.tenant_id
                    WHERE i.source_quotation_version_id IS NOT NULL
                      AND i.business_id IS DISTINCT FROM qv.business_id
                ) THEN
                    RAISE EXCEPTION
                        'Invoice business does not match source quotation version business';
                END IF;
            END
            $$
            SQL
        );

        /*
         * =========================================================
         * 3. BACKFILL CHILDREN FROM INVOICE ROOT
         * =========================================================
         */

        DB::statement(
            <<<'SQL'
            UPDATE invoice_items AS ii
            SET business_id = i.business_id
            FROM invoices AS i
            WHERE i.id = ii.invoice_id
              AND i.tenant_id = ii.tenant_id
              AND ii.business_id IS NULL
            SQL
        );

        DB::statement(
            <<<'SQL'
            UPDATE invoice_status_history AS ish
            SET business_id = i.business_id
            FROM invoices AS i
            WHERE i.id = ish.invoice_id
              AND i.tenant_id = ish.tenant_id
              AND ish.business_id IS NULL
            SQL
        );

        /*
         * Fail closed before NOT NULL.
         */
        DB::statement(
            <<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (
                    SELECT 1
                    FROM invoices
                    WHERE business_id IS NULL
                ) THEN
                    RAISE EXCEPTION
                        'Invoice business backfill incomplete';
                END IF;

                IF EXISTS (
                    SELECT 1
                    FROM invoice_items
                    WHERE business_id IS NULL
                ) THEN
                    RAISE EXCEPTION
                        'Invoice item business backfill incomplete';
                END IF;

                IF EXISTS (
                    SELECT 1
                    FROM invoice_status_history
                    WHERE business_id IS NULL
                ) THEN
                    RAISE EXCEPTION
                        'Invoice history business backfill incomplete';
                END IF;
            END
            $$
            SQL
        );

        /*
         * =========================================================
         * 4. CONTRACT NULLABILITY
         * =========================================================
         */

        DB::statement(
            <<<'SQL'
            ALTER TABLE invoices
            ALTER COLUMN business_id SET NOT NULL
            SQL
        );

        DB::statement(
            <<<'SQL'
            ALTER TABLE invoice_items
            ALTER COLUMN business_id SET NOT NULL
            SQL
        );

        DB::statement(
            <<<'SQL'
            ALTER TABLE invoice_status_history
            ALTER COLUMN business_id SET NOT NULL
            SQL
        );

        /*
         * =========================================================
         * 5. ROOT CANDIDATE KEY
         *
         * Keep existing UNIQUE(id, tenant_id).
         * Payment allocation still depends on it.
         * =========================================================
         */

        DB::statement(
            <<<'SQL'
            ALTER TABLE invoices
            ADD CONSTRAINT invoices_id_business_id_tenant_id_unique
            UNIQUE (id, business_id, tenant_id)
            SQL
        );

        /*
         * =========================================================
         * 6. BUSINESS-SCOPED UNIQUENESS
         * =========================================================
         */

        DB::statement(
            <<<'SQL'
            ALTER TABLE invoices
            DROP CONSTRAINT invoices_tenant_id_invoice_number_unique
            SQL
        );

        DB::statement(
            <<<'SQL'
            ALTER TABLE invoices
            ADD CONSTRAINT invoices_tenant_business_invoice_number_unique
            UNIQUE (tenant_id, business_id, invoice_number)
            SQL
        );

        DB::statement(
            <<<'SQL'
            ALTER TABLE invoices
            DROP CONSTRAINT invoices_tenant_id_source_quotation_version_id_unique
            SQL
        );

        DB::statement(
            <<<'SQL'
            ALTER TABLE invoices
            ADD CONSTRAINT invoices_tenant_business_source_version_unique
            UNIQUE (
                tenant_id,
                business_id,
                source_quotation_version_id
            )
            SQL
        );

        /*
         * =========================================================
         * 7. SAME BUSINESS + TENANT FOREIGN KEYS
         * =========================================================
         */

        DB::statement(
            <<<'SQL'
            ALTER TABLE invoices
            ADD CONSTRAINT invoices_customer_business_tenant_foreign
            FOREIGN KEY (
                customer_id,
                business_id,
                tenant_id
            )
            REFERENCES customers (
                id,
                business_id,
                tenant_id
            )
            SQL
        );

        DB::statement(
            <<<'SQL'
            ALTER TABLE invoices
            ADD CONSTRAINT invoices_source_quotation_business_tenant_foreign
            FOREIGN KEY (
                source_quotation_id,
                business_id,
                tenant_id
            )
            REFERENCES quotations (
                id,
                business_id,
                tenant_id
            )
            SQL
        );

        DB::statement(
            <<<'SQL'
            ALTER TABLE invoices
            ADD CONSTRAINT invoices_source_version_business_tenant_foreign
            FOREIGN KEY (
                source_quotation_version_id,
                business_id,
                tenant_id
            )
            REFERENCES quotation_versions (
                id,
                business_id,
                tenant_id
            )
            SQL
        );

        DB::statement(
            <<<'SQL'
            ALTER TABLE invoice_items
            ADD CONSTRAINT invoice_items_invoice_business_tenant_foreign
            FOREIGN KEY (
                invoice_id,
                business_id,
                tenant_id
            )
            REFERENCES invoices (
                id,
                business_id,
                tenant_id
            )
            ON DELETE CASCADE
            SQL
        );

        DB::statement(
            <<<'SQL'
            ALTER TABLE invoice_items
            ADD CONSTRAINT invoice_items_catalog_business_tenant_foreign
            FOREIGN KEY (
                catalog_item_id,
                business_id,
                tenant_id
            )
            REFERENCES catalog_items (
                id,
                business_id,
                tenant_id
            )
            SQL
        );

        /*
         * source_quotation_item_id is nullable, but when present
         * it must belong to the same Business + Tenant.
         */
        DB::statement(
            <<<'SQL'
            ALTER TABLE invoice_items
            ADD CONSTRAINT invoice_items_source_quote_item_business_tenant_foreign
            FOREIGN KEY (
                source_quotation_item_id,
                business_id,
                tenant_id
            )
            REFERENCES quotation_items (
                id,
                business_id,
                tenant_id
            )
            SQL
        );

        DB::statement(
            <<<'SQL'
            ALTER TABLE invoice_status_history
            ADD CONSTRAINT invoice_history_invoice_business_tenant_foreign
            FOREIGN KEY (
                invoice_id,
                business_id,
                tenant_id
            )
            REFERENCES invoices (
                id,
                business_id,
                tenant_id
            )
            ON DELETE CASCADE
            SQL
        );

        /*
         * =========================================================
         * 8. BUSINESS-SCOPED READ INDEXES
         * =========================================================
         */

        DB::statement(
            <<<'SQL'
            CREATE INDEX invoices_tenant_business_status_idx
            ON invoices (
                tenant_id,
                business_id,
                status
            )
            SQL
        );

        DB::statement(
            <<<'SQL'
            CREATE INDEX invoices_tenant_business_customer_idx
            ON invoices (
                tenant_id,
                business_id,
                customer_id
            )
            SQL
        );

        DB::statement(
            <<<'SQL'
            CREATE INDEX invoices_tenant_business_due_at_idx
            ON invoices (
                tenant_id,
                business_id,
                due_at
            )
            SQL
        );

        DB::statement(
            <<<'SQL'
            CREATE INDEX invoice_items_tenant_business_invoice_sort_idx
            ON invoice_items (
                tenant_id,
                business_id,
                invoice_id,
                sort_order
            )
            SQL
        );

        DB::statement(
            <<<'SQL'
            CREATE INDEX invoice_history_tenant_business_invoice_time_idx
            ON invoice_status_history (
                tenant_id,
                business_id,
                invoice_id,
                occurred_at
            )
            SQL
        );

        DB::statement(
            <<<'SQL'
            CREATE INDEX invoices_receivable_business_lookup_idx
            ON invoices (
                tenant_id,
                business_id,
                status,
                outstanding_amount
            )
            SQL
        );
    }

    public function down(): void
    {
        /*
         * F9 is a contract migration.
         *
         * Production/staging recovery must use the verified database
         * backup rather than destructive schema rollback after
         * business-specific invoice numbers have been created.
         */
        throw new RuntimeException(
            'Irreversible migration: restore from verified backup.'
        );
    }
};
