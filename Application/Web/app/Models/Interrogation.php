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
        'role',          // 'user' | 'assistant'
        'content',       // message content (question/answer)
        'chat_id',       // optional owner/initiator
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
