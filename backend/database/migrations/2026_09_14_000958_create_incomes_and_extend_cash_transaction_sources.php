<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'incomes',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();

                $table->ulid('tenant_id');

                /*
                 * Boleh NULL selama DRAFT.
                 * Wajib ACTIVE cash account ketika POST
                 * melalui domain/service transition.
                 */
                $table->ulid(
                    'cash_account_id'
                )->nullable();

                $table->decimal(
                    'amount',
                    18,
                    2
                );

                $table->string(
                    'currency',
                    3
                )->default('IDR');

                $table->timestampTz(
                    'occurred_at'
                );

                /*
                 * Starter menggunakan kategori teks sederhana.
                 * Jangan membuat master category dahulu.
                 */
                $table->string(
                    'category',
                    100
                )->nullable();

                $table->text(
                    'description'
                );

                $table->string(
                    'reference',
                    190
                )->nullable();

                $table->string(
                    'status',
                    16
                )->default('DRAFT');

                $table->ulid(
                    'created_by_user_id'
                )->nullable();

                $table->ulid(
                    'posted_by_user_id'
                )->nullable();

                $table->timestampTz(
                    'posted_at'
                )->nullable();

                $table->ulid(
                    'voided_by_user_id'
                )->nullable();

                $table->timestampTz(
                    'voided_at'
                )->nullable();

                $table->text(
                    'void_reason'
                )->nullable();

                $table->timestampsTz();

                $table->foreign(
                    'tenant_id'
                )
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();

                /*
                 * Composite FK memastikan rekening
                 * selalu berasal dari tenant yang sama.
                 */
                $table->foreign([
                    'cash_account_id',
                    'tenant_id',
                ])
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('cash_accounts');

                $table->foreign([
                    'tenant_id',
                    'created_by_user_id',
                ])
                    ->references([
                        'tenant_id',
                        'user_id',
                    ])
                    ->on('tenant_users');

                $table->foreign([
                    'tenant_id',
                    'posted_by_user_id',
                ])
                    ->references([
                        'tenant_id',
                        'user_id',
                    ])
                    ->on('tenant_users');

                $table->foreign([
                    'tenant_id',
                    'voided_by_user_id',
                ])
                    ->references([
                        'tenant_id',
                        'user_id',
                    ])
                    ->on('tenant_users');

                /*
                 * Mendukung composite references
                 * tenant-safe bila dibutuhkan nanti.
                 */
                $table->unique([
                    'id',
                    'tenant_id',
                ]);

                $table->index([
                    'tenant_id',
                    'status',
                    'occurred_at',
                ]);

                $table->index([
                    'tenant_id',
                    'cash_account_id',
                    'occurred_at',
                ]);
            }
        );

        DB::statement(
            "ALTER TABLE incomes
             ADD CONSTRAINT incomes_amount_positive_check
             CHECK (amount > 0)"
        );

        DB::statement(
            "ALTER TABLE incomes
             ADD CONSTRAINT incomes_currency_check
             CHECK (currency = 'IDR')"
        );

        DB::statement(
            "ALTER TABLE incomes
             ADD CONSTRAINT incomes_status_check
             CHECK (
                status IN (
                    'DRAFT',
                    'POSTED',
                    'VOID'
                )
             )"
        );

        /*
         * Migration foundation lama sudah berjalan.
         * Ubah constraint secara forward-only.
         */
        DB::statement(
            "ALTER TABLE cash_transactions
             DROP CONSTRAINT cash_transactions_source_type_check"
        );

        DB::statement(
            "ALTER TABLE cash_transactions
             ADD CONSTRAINT cash_transactions_source_type_check
             CHECK (
                source_type IN (
                    'PAYMENT',
                    'PAYMENT_REVERSAL',
                    'MANUAL_INCOME',
                    'MANUAL_INCOME_VOID',
                    'EXPENSE',
                    'EXPENSE_VOID'
                )
             )"
        );
    }

    public function down(): void
    {
        DB::statement(
            "ALTER TABLE cash_transactions
             DROP CONSTRAINT cash_transactions_source_type_check"
        );

        /*
         * Kembalikan constraint ke bentuk foundation
         * sebelum migration C.2.
         */
        DB::statement(
            "ALTER TABLE cash_transactions
             ADD CONSTRAINT cash_transactions_source_type_check
             CHECK (
                source_type IN (
                    'PAYMENT',
                    'PAYMENT_REVERSAL',
                    'MANUAL_INCOME',
                    'EXPENSE',
                    'EXPENSE_VOID'
                )
             )"
        );

        Schema::dropIfExists(
            'incomes'
        );
    }
};
