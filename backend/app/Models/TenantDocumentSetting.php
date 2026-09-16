<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantDocumentSetting extends Model
{
    use HasUlids;

    public const DEFAULT_QUOTATION_OPENING_TEXT =
        "Dengan hormat,\n"
        . "Bersama ini kami menyampaikan penawaran atas kebutuhan "
        . "pekerjaan/barang dan jasa sesuai rincian berikut.";

    public const DEFAULT_QUOTATION_CLOSING_TEXT =
        "Demikian penawaran ini kami sampaikan. Besar harapan kami "
        . "dapat bekerja sama dengan Bapak/Ibu.\n"
        . "Terima kasih atas perhatian dan kepercayaannya.";

    public const DEFAULT_INVOICE_FOOTNOTE =
        "Mohon melakukan pembayaran sesuai nilai dan jatuh tempo "
        . "yang tercantum pada tagihan.\n"
        . "Pembayaran dianggap sah setelah dana diterima dan tercatat "
        . "oleh pihak kami.";

    public const DEFAULT_QUOTATION_TERMS =
        "- Harga berlaku sesuai masa berlaku penawaran.\n"
        . "- Perubahan lingkup pekerjaan dapat memengaruhi harga "
        . "dan waktu pengerjaan.\n"
        . "- Pekerjaan dimulai setelah persetujuan dan ketentuan "
        . "pembayaran terpenuhi.";

    public const DEFAULT_QUOTATION_VALIDITY_DAYS = 14;

    protected $fillable = [
        'tenant_id',
        'business_id',

        /*
         * Legacy identity columns.
         *
         * Dipertahankan sementara selama progressive migration.
         * Source of truth identitas usaha akan pindah ke
         * business_profiles pada F6B.
         */
        'business_name',
        'address',
        'phone',
        'email',
        'tax_id',

        'quotation_footer',

        'quotation_opening_text',
        'quotation_closing_text',
        'quotation_default_terms',
        'quotation_default_validity_days',
        'quotation_number_prefix',
        'invoice_number_prefix',

        'invoice_footnote',
        'invoice_template_key',
        'invoice_palette_key',
        'signature_name',
        'signature_title',
        'signature_image_file_id',
    ];

    protected $casts = [
        'quotation_default_validity_days' => 'integer',
    ];

    public function signatureImage(): BelongsTo
    {
        return $this->belongsTo(
            FileAsset::class,
            'signature_image_file_id'
        );
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(
            BusinessProfile::class,
            'business_id'
        );
    }
}
