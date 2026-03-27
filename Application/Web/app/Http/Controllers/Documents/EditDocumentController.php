<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\EditInterrogation;
use App\Models\Upload;
use Illuminate\Http\Request;
use Inertia\Inertia;
use MongoDB\BSON\ObjectId;

class EditDocumentController extends Controller
{
    private ?string $documentId = null;
    private ?string $chatId = null;
    private ?Upload $document = null;

    public function __construct(private Request $request)
    {
        $this->documentId = $this->request->get('id');
        $this->chatId = $this->request->get('chat_id');
        $this->document = Upload::find($this->documentId);
    }

    public function __invoke()
    {
        if (blank($this->document)) {
            return redirect()->back()->with('error', 'Document not found.');
        }

        $chatData = $this->getEditChatData($this->chatId);

        return Inertia::render('documents/EditDocument', [
            'document' => $this->document,
            'chatData' => $chatData,
        ]);
    }

    private function getEditChatData(?string $chatId = null): array
    {
        $data = [
            'id'    => $chatId,
            'title' => "...",
            'messages' => []
        ];

        // if (blank($chatId) || !preg_match('/^[a-f0-9]{24}$/i', $chatId)) {
        //     return $data;
        // }

        $id = new ObjectId($chatId);

        // $messages = EditInterrogation::query()->where('chat_id', $id)->orderBy('created_at', 'asc')->get();
        $messages = [
            (object)[
                'role' => 'user',
                'content' => 'Tradu acest document în engleză.',
                'reasoning' => null,
            ],
            (object)[
                'role' => 'assistant',
                'content' => 'Sure! Here is the translation of the document into English.',
                'reasoning' => 'The user requested a translation of the document into English, so I provided the translation as requested.',
            ],
        ];

        foreach ($messages as $message) {
            $data['messages'][] = [
                'role' => $message->role,
                'content' => $message->content,
                'reasoning' => $message->reasoning,
            ];
        }

        return $data;
    }
}
