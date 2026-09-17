<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $table = 'invoices';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'business_id',
        'invoice_number',
        'customer_id',
        'project_id',
        'source_quotation_id',
        'source_quotation_version_id',
        'status',
        'issued_at',
        'due_at',
        'currency',
        'subtotal',
        'discount_total',
        'item_discount_total',
        'global_discount_type',
        'global_discount_value',
        'global_discount_amount',
        'tax_enabled',
        'tax_rate',
        'tax_total',
        'total',
        'paid_amount',
        'outstanding_amount',
        'invoice_template_key',
        'invoice_palette_key',
        'invoice_template_version',
        'branding_snapshot',
        'branding_logo_file_id',
        'branding_signature_file_id',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'due_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'item_discount_total' => 'decimal:2',
        'global_discount_value' => 'decimal:4',
        'global_discount_amount' => 'decimal:2',
        'tax_enabled' => 'boolean',
        'tax_rate' => 'decimal:4',
        'tax_total' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'outstanding_amount' => 'decimal:2',
        'invoice_template_version' => 'integer',
        'branding_snapshot' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(
            Customer::class
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            InvoiceItem::class
        );
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(
            PaymentAllocation::class
        );
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(
            InvoiceStatusHistory::class
        );
    }
}
