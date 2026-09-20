<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderStatusHistory extends Model
{
    use HasUlids;

    protected $table =
        'purchase_order_status_history';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'business_id',
        'purchase_order_id',
        'from_status',
        'to_status',
        'action',
        'actor_user_id',
        'reason',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(
            PurchaseOrder::class
        );
    }
}
