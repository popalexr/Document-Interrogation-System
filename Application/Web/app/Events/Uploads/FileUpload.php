<?php

namespace App\Events\Uploads;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FileUpload
{
    use Dispatchable, SerializesModels;

    public string $fileId;

    /**
     * Create a new event instance.
    
     * @param string $fileId The ID of the uploaded file
     */
    public function __construct(string $fileId)
    {
        $this->fileId = $fileId;
    }
}
