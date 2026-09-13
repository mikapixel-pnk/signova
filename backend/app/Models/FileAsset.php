<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileAsset extends Model
{
    protected $table = 'files';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'purpose',
        'storage_disk',
        'object_key',
        'original_name',
        'mime_type',
        'size_bytes',
        'checksum_sha256',
        'visibility',
        'uploaded_by_user_id',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(
            Tenant::class
        );
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'uploaded_by_user_id'
        );
    }
}
