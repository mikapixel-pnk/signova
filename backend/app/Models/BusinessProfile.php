<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class BusinessProfile extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'name',
        'legal_name',
        'address',
        'city',
        'province',
        'postal_code',
        'phone',
        'whatsapp',
        'email',
        'website',
        'tax_id',
        'logo_file_id',
        'is_default',
        'status',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];
}
