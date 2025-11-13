<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;
use MongoDB\Laravel\Relations\BelongsTo;

class Upload extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'uploads';

    protected $fillable = [
        'user_id',
        'original_name',
        'mime_type',
        'size',
        'checksum',
        'r2_bucket',
        'r2_key',
        'status',
        'meta',
    ];

    protected $casts = [
        'size' => 'integer',
        'meta' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
