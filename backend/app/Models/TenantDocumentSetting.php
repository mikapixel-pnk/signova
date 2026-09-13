<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantDocumentSetting extends Model
{
    protected $primaryKey = 'tenant_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'business_name',
        'address',
        'phone',
        'email',
        'tax_id',
        'quotation_footer',
        'invoice_footnote',
        'signature_name',
        'signature_title',
        'signature_image_file_id',
    ];

    public function signatureImage(): BelongsTo
    {
        return $this->belongsTo(
            FileAsset::class,
            'signature_image_file_id'
        );
    }
}
