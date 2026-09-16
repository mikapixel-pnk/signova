<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class CatalogCategory extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'business_id',
        'code',
        'name',
        'description',
        'status',
    ];
}
