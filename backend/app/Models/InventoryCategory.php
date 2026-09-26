<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryCategory extends Model
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

    public function materials(): HasMany
    {
        return $this->hasMany(
            Material::class,
            'category_id'
        );
    }
}
