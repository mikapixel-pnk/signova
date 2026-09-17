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
        Schema::table(
            'cash_accounts',
            function (Blueprint $table): void {
                $table->boolean(
                    'accepts_payments'
                )
                    ->default(false)
                    ->after('is_default');

                $table->index(
                    [
                        'tenant_id',
                        'business_id',
                        'status',
                        'accepts_payments',
                        'type',
                    ],
                    'cash_accounts_payment_visibility_index'
                );
            }
        );

        /*
         * Progressive backfill:
         *
         * Rekening transfer lama masih berada pada
         * tenant_payment_settings. Salin ke cash_accounts
         * tanpa menghapus data legacy.
         *
         * Existing matching account tidak diduplikasi.
         */
        DB::table('tenant_payment_settings')
            ->select([
                'tenant_id',
                'business_id',
                'bank_transfer_enabled',
                'bank_name',
                'bank_account_number',
                'bank_account_name',
            ])
            ->orderBy('tenant_id')
            ->orderBy('business_id')
            ->get()
            ->each(
                function ($settings): void {
                    $bankName =
                        trim(
                            (string) (
                                $settings->bank_name
                                ?? ''
                            )
                        );

                    $accountNumber =
                        trim(
                            (string) (
                                $settings
                                    ->bank_account_number
                                ?? ''
                            )
                        );

                    $accountName =
                        trim(
                            (string) (
                                $settings
                                    ->bank_account_name
                                ?? ''
                            )
                        );

                    /*
                     * BANK canonical membutuhkan nama bank
                     * dan nama pemilik rekening.
                     */
                    if (
                        $bankName === ''
                        || $accountName === ''
                    ) {
                        return;
                    }

                    $existingQuery =
                        DB::table('cash_accounts')
                            ->where(
                                'tenant_id',
                                $settings->tenant_id
                            )
                            ->where(
                                'business_id',
                                $settings->business_id
                            )
                            ->where(
                                'type',
                                'BANK'
                            );

                    if ($accountNumber !== '') {
                        $existingQuery->where(
                            'account_number',
                            $accountNumber
                        );
                    } else {
                        $existingQuery
                            ->where(
                                'bank_name',
                                $bankName
                            )
                            ->where(
                                'account_name',
                                $accountName
                            );
                    }

                    $existing =
                        $existingQuery->first();

                    if ($existing !== null) {
                        /*
                         * Jangan matikan pilihan yang sudah ada.
                         * Legacy transfer=ON hanya boleh menaikkan
                         * visibility menjadi true.
                         */
                        if (
                            (bool) $settings
                                ->bank_transfer_enabled
                            && ! (bool) $existing
                                ->accepts_payments
                        ) {
                            DB::table('cash_accounts')
                                ->where(
                                    'id',
                                    $existing->id
                                )
                                ->update([
                                    'accepts_payments' =>
                                        true,

                                    'updated_at' =>
                                        now(),
                                ]);
                        }

                        return;
                    }

                    $hasAccount =
                        DB::table('cash_accounts')
                            ->where(
                                'tenant_id',
                                $settings->tenant_id
                            )
                            ->where(
                                'business_id',
                                $settings->business_id
                            )
                            ->exists();

                    DB::table('cash_accounts')
                        ->insert([
                            'id' =>
                                (string) Str::ulid(),

                            'tenant_id' =>
                                $settings->tenant_id,

                            'business_id' =>
                                $settings->business_id,

                            'name' =>
                                'Rekening Penerimaan '
                                . $bankName,

                            'type' =>
                                'BANK',

                            'bank_name' =>
                                $bankName,

                            'account_number' =>
                                $accountNumber !== ''
                                    ? $accountNumber
                                    : null,

                            'account_name' =>
                                $accountName,

                            'currency' =>
                                'IDR',

                            'status' =>
                                'ACTIVE',

                            'is_default' =>
                                ! $hasAccount,

                            'accepts_payments' =>
                                (bool) $settings
                                    ->bank_transfer_enabled,

                            /*
                             * Legacy settings tidak menyimpan
                             * actor pembuat rekening. Kolom ini
                             * memang nullable.
                             */
                            'created_by_user_id' =>
                                null,

                            'created_at' =>
                                now(),

                            'updated_at' =>
                                now(),
                        ]);
                }
            );
    }

    public function down(): void
    {
        /*
         * Rekening hasil backfill sengaja tidak dihapus.
         * Finance data tidak boleh hilang hanya karena
         * schema rollback.
         */
        Schema::table(
            'cash_accounts',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'cash_accounts_payment_visibility_index'
                );

                $table->dropColumn(
                    'accepts_payments'
                );
            }
        );
    }
};
