<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
    ];
}
