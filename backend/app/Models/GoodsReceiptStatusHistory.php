<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class GoodsReceiptStatusHistory extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $table =
        'goods_receipt_status_history';

    protected $fillable = [
        'tenant_id',
        'business_id',
        'goods_receipt_id',
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
}
