<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;
use MongoDB\Laravel\Relations\BelongsTo;

class EditChat extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'edit_chats';

    protected $fillable = [
        'document_id',   // string/ObjectId of the related Upload
        'edit_id',       // reference to the Edit that this chat belongs to
        'user_id',       // optional owner/initiator
        'title',         // generated title for the chat
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
