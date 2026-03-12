<?php

namespace App\Http\Controllers\Chats;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chats\DeleteAllChatsForDocumentRequest;
use App\Models\Chat;

class DeleteAllChatsForDocumentController extends Controller
{
    public function __invoke(DeleteAllChatsForDocumentRequest $request)
    {
        Chat::query()
            ->where('document_id', $request['document_id'])
            ->delete();

        return redirect()->back()->with("success", "All chats for the document have been deleted.");
    }
}
