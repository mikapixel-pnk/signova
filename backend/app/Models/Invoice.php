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
        'tax_total',
        'total',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'due_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total' => 'decimal:2',
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
