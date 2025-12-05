<?php

namespace App\Observers;

use App\Models\Interrogation;
use App\Models\Upload;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class UploadObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * Handle the Upload "deleted" event.
     * 
     * This method is triggered when an upload is deleted
     * and will delete any documents from all related collections where this upload is referenced.
     */
    public function deleted(Upload $upload): void
    {
        Interrogation::where('document_id', $upload->_id)
            ->delete();
    }
}
