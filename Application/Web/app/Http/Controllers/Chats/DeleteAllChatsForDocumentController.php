<?php

namespace App\Http\Controllers\Chats;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chats\DeleteAllChatsForDocumentRequest;
use App\Models\Chat;
use App\Models\Upload;

class DeleteAllChatsForDocumentController extends Controller
{
    public function __invoke(DeleteAllChatsForDocumentRequest $request)
    {
        $document = $this->getDocumentById($request['document_id']);

        if (blank($document)) {
            return redirect()->back()->with("error", "Document not found.");
        }

        if ($document->user_id !== $request->user()->id) {
            return redirect()->back()->with("error", "Document not found.");
        }

        Chat::query()
            ->where('document_id', $request['document_id'])
            ->delete();

        return redirect()->back()->with("success", "All chats for the document have been deleted.");
    }

    private function getDocumentById(string $documentId)
    {
        return Upload::find($documentId);
    }
}
