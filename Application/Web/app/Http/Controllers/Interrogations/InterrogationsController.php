<?php

namespace App\Http\Controllers\Interrogations;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Upload;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class InterrogationsController extends Controller
{
    private ?string $interrogationId;
    private $userId;

    public function __construct(private Request $request)
    {
        $this->interrogationId = $request->get('id', null);
        $this->userId = optional($request->user())->getKey();
    }

    public function __invoke(): Response
    {
        return Inertia::render('interrogations/Index', [
            'documents' => $this->getUserDocuments(),
            'chats' => $this->getUserChats(),
            'interrogation_id' => $this->interrogationId,
        ]);
    }

    private function getUserDocuments(): Collection
    {
        if (blank($this->userId)) {
            return collect();
        }

        return Upload::query()
            ->where('user_id', $this->userId)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get([
                '_id',
                'original_name',
                'mime_type',
                'size',
                'status',
                'created_at',
                'updated_at',
            ])
            ->map(fn (Upload $upload) => [
                '_id' => (string) $upload->_id,
                'original_name' => $upload->original_name,
                'mime_type' => $upload->mime_type,
                'size' => (int) $upload->size,
                'status' => (string) $upload->status,
                'created_at' => $upload->created_at,
                'updated_at' => $upload->updated_at,
            ]);
    }

    private function getUserChats(): Collection
    {
        if (blank($this->userId)) {
            return collect();
        }

        return Chat::query()
            ->where('user_id', $this->userId)
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get(['_id', 'document_id', 'title', 'created_at', 'updated_at'])
            ->map(fn (Chat $chat) => [
                'chat_id' => (string) $chat->_id,
                'title' => $chat->title ?: 'Untitled chat',
                'document_count' => 1,
                'updated_at' => $chat->updated_at ?? $chat->created_at,
            ]);
    }
}
