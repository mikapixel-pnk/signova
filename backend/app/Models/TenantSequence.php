<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantSequence extends Model
{
    protected $table =
        'tenant_sequences';

    protected $keyType =
        'string';

    public $incrementing =
        false;

    protected $fillable = [
        'id',
        'tenant_id',
        'business_id',
        'document_type',
        'period',
        'prefix',
        'next_number',
        'padding',
    ];

    protected $casts = [
        'next_number' => 'integer',
        'padding' => 'integer',
    ];
}
