<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;
use MongoDB\Laravel\Relations\BelongsTo;

/**
 * AI Interrogation Chat model
 *
 * Represents all chats associated with the AI Interrogations module.
 */
class AIInterrogationChat extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'ai_interrogation_chats';

    protected $fillable = [
        'user_id',       // optional owner/initiator
        'title',         // generated title for the chat
    ];

    protected $casts = [
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
