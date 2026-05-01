<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;

/**
 * AI Interrogation model
 *
 * Represents all AI Interrogation module messages associated with a chat.
 */
class AIInterrogation extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'ai_interrogations';

    protected $fillable = [
        'role',          // 'user' | 'assistant'
        'content',       // message content (question/answer)
        'chat_id',       // optional owner/initiator
        'documents_ids', // list of document ids used as context
    ];

    protected $casts = [
        'documents_ids' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
