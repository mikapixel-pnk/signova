<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuotationVersion extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'quotation_id',
        'revision_no',
        'subtotal',
        'discount_total',
        'tax_total',
        'total',
        'currency',
        'terms',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'revision_no' => 'integer',
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(
            Quotation::class
        );
    }

    public function publicLinks(): HasMany
    {
        return $this->hasMany(
            QuotationPublicLink::class,
            'quotation_version_id'
        );
    }

    public function actions(): HasMany
    {
        return $this->hasMany(
            QuotationAction::class,
            'quotation_version_id'
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            QuotationItem::class
        )->orderBy('sort_order');
    }
}
