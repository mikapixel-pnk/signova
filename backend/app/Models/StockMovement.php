<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'business_id',
        'material_id',
        'warehouse_id',
        'type',
        'quantity_signed',
        'source_type',
        'source_id',
        'occurred_at',
        'actor_user_id',
        'reason',
        'reversal_of_movement_id',
        'created_at',
    ];

    protected $casts = [
        'quantity_signed' => 'decimal:4',
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
