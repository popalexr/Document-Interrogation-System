<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;
use MongoDB\Laravel\Relations\BelongsTo;

/**
 * Interrogation model
 *
 * Represents all interrogations (Q&A) associated with a single upload/document.
 */
class Interrogation extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'interrogations';

    protected $fillable = [
        // Per-message document fields (as used by controller)
        'document_id',   // string/ObjectId of the related Upload
        'role',          // 'user' | 'assistant'
        'content',       // message content (question/answer)
        'user_id',       // optional owner/initiator

        // Optional conversation style aggregate fields (kept for flexibility)
        'upload_id',
        'entries',
        'meta',
    ];

    protected $casts = [
        'entries' => 'array',
        'meta' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function upload(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
