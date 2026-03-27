<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;
use MongoDB\Laravel\Relations\BelongsTo;

class Edit extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'edits';

    protected $fillable = [
        'original_document_id', // reference to the original document
        'user_id',
        'original_name',
        'r2_key',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function upload(): BelongsTo
    {
        return $this->belongsTo(Upload::class, 'original_document_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
