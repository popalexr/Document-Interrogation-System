<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;
use MongoDB\Laravel\Relations\BelongsTo;

/**
 * Chat model
 *
 * Represents all chats associated with a single upload/document.
 */
class Chat extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'chats';

    protected $fillable = [
        'document_id',   // string/ObjectId of the related Upload
        'user_id',       // optional owner/initiator
        'upload_id',
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
