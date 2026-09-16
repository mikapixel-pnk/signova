<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'tenant_document_settings',
            function (Blueprint $table): void {
                $table->text(
                    'quotation_default_terms'
                )->nullable();

                $table->unsignedSmallInteger(
                    'quotation_default_validity_days'
                )->nullable();
            }
        );

        DB::statement(
            'ALTER TABLE tenant_document_settings '
            . 'ADD CONSTRAINT tenant_document_settings_validity_days_check '
            . 'CHECK (quotation_default_validity_days IS NULL '
            . 'OR quotation_default_validity_days BETWEEN 1 AND 365)'
        );

        $opening = <<<'TEXT'
Dengan hormat,
Bersama ini kami menyampaikan penawaran atas kebutuhan pekerjaan/barang dan jasa sesuai rincian berikut.
TEXT;

        $closing = <<<'TEXT'
Demikian penawaran ini kami sampaikan. Besar harapan kami dapat bekerja sama dengan Bapak/Ibu.
Terima kasih atas perhatian dan kepercayaannya.
TEXT;

        $invoiceFootnote = <<<'TEXT'
Mohon melakukan pembayaran sesuai nilai dan jatuh tempo yang tercantum pada tagihan.
Pembayaran dianggap sah setelah dana diterima dan tercatat oleh pihak kami.
TEXT;

        $terms = <<<'TEXT'
- Harga berlaku sesuai masa berlaku penawaran.
- Perubahan lingkup pekerjaan dapat memengaruhi harga dan waktu pengerjaan.
- Pekerjaan dimulai setelah persetujuan dan ketentuan pembayaran terpenuhi.
TEXT;

        DB::table('tenant_document_settings')
            ->whereNull('quotation_opening_text')
            ->update([
                'quotation_opening_text' => $opening,
            ]);

        DB::table('tenant_document_settings')
            ->whereNull('quotation_closing_text')
            ->update([
                'quotation_closing_text' => $closing,
            ]);

        DB::table('tenant_document_settings')
            ->whereNull('invoice_footnote')
            ->update([
                'invoice_footnote' => $invoiceFootnote,
            ]);

        DB::table('tenant_document_settings')
            ->whereNull('quotation_default_terms')
            ->update([
                'quotation_default_terms' => $terms,
            ]);

        DB::table('tenant_document_settings')
            ->whereNull('quotation_default_validity_days')
            ->update([
                'quotation_default_validity_days' => 14,
            ]);
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE tenant_document_settings '
            . 'DROP CONSTRAINT IF EXISTS '
            . 'tenant_document_settings_validity_days_check'
        );

        Schema::table(
            'tenant_document_settings',
            function (Blueprint $table): void {
                $table->dropColumn([
                    'quotation_default_terms',
                    'quotation_default_validity_days',
                ]);
            }
        );
    }
};
