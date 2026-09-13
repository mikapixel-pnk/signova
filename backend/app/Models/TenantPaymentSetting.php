<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantPaymentSetting extends Model
{
    protected $primaryKey = 'tenant_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'bank_transfer_enabled',
        'bank_name',
        'bank_account_number',
        'bank_account_name',
        'static_qr_enabled',
        'static_qr_file_id',
        'midtrans_enabled',
        'partial_payment_enabled',
    ];

    protected $casts = [
        'bank_transfer_enabled' => 'boolean',
        'static_qr_enabled' => 'boolean',
        'midtrans_enabled' => 'boolean',
        'partial_payment_enabled' => 'boolean',
    ];

    public function staticQrFile(): BelongsTo
    {
        return $this->belongsTo(
            FileAsset::class,
            'static_qr_file_id'
        );
    }
}
