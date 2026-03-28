<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;

class EditInterrogation extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'edit_interrogations';

    protected $fillable = [
        'chat_id',
        'role',          // 'user' | 'assistant'
        'reasoning',     // optional explanation for the edit
        'content',       // message content (question/answer)
        'edit_document_id', // reference to the edited document (if applicable)
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
