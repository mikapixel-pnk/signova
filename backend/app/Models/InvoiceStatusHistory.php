<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceStatusHistory extends Model
{
    protected $table =
        'invoice_status_history';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'business_id',
        'invoice_id',
        'from_state',
        'to_state',
        'actor_user_id',
        'reason',
        'source',
        'context',
        'occurred_at',
    ];

    protected $casts = [
        'context' => 'array',
        'occurred_at' => 'datetime',
    ];
}
