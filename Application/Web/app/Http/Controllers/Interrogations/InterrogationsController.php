<?php

namespace App\Http\Controllers\Interrogations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Interrogations\AIInterrogationRequest;
use App\Models\AIInterrogation;
use App\Models\AIInterrogationChat;
use App\Models\Upload;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use MongoDB\BSON\ObjectId;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $chatId = $this->getActiveChatId();

        return Inertia::render('interrogations/Index', [
            'documents' => $this->getUserDocuments(),
            'chats' => $this->getUserChats(),
            'interrogations' => $chatId ? $this->getHistoryQuery($chatId) : [],
            'selected_documents_ids' => $chatId ? $this->getChatDocumentsIds($chatId) : [],
            'interrogation_id' => $chatId,
            'chat_id' => $chatId,
        ]);
    }

    public function store(AIInterrogationRequest $request)
    {
        $documentsIds = array_values(array_unique($request->validated('documents_ids')));
        $chatId = $request->validated('chat_id');
        $newChat = false;

        if (!$this->userOwnsDocuments($documentsIds)) {
            abort(422, 'One or more selected documents are invalid.');
        }

        if (blank($chatId)) {
            $chatId = $this->createNewChat();
            $newChat = true;
        } elseif (!$this->existsChat($chatId)) {
            abort(404);
        }

        $payload = [
            'documents_ids' => $documentsIds,
            'user_id' => $this->userId,
            'question' => $request['query'],
            'extra' => [
                'history' => $this->getHistoryQuery($chatId),
            ],
        ];

        $this->storeAIInterrogation([
            'chat_id' => $chatId,
            'documents_ids' => $documentsIds,
            'role' => 'user',
            'content' => $request['query'],
        ]);

        $mcpUrl = sprintf(
            '%s:%s%s',
            config('mcp.host'),
            config('mcp.port'),
            config('mcp.ai_interrogation_endpoint')
        );

        $client = new Client([
            'timeout' => 120,
            'connect_timeout' => 10,
            'http_errors' => false,
        ]);

        try {
            $response = $client->post($mcpUrl, [
                'stream' => true,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'text/event-stream',
                ],
                'body' => json_encode($payload),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to communicate with MCP server.',
                'message' => $e->getMessage(),
            ], 500);
        }

        $status = $response->getStatusCode();
        if ($status >= 400) {
            return response()->json([
                'error' => 'MCP server returned an error status.',
                'status' => $status,
            ], 500);
        }

        $bodyStream = $response->getBody();

        $streamCallback = function () use ($bodyStream, $chatId, $documentsIds, $newChat) {
            @set_time_limit(0);
            @ini_set('max_execution_time', '0');
            @ini_set('output_buffering', '0');
            @ini_set('zlib.output_compression', '0');
            while (ob_get_level() > 0) { @ob_end_flush(); }
            ob_implicit_flush(true);

            $buffer = '';
            $answer = '';

            try {
                echo ": stream start\n\n";
                @ob_flush();
                flush();

                while (!$bodyStream->eof()) {
                    $chunk = $bodyStream->read(1024);

                    if ($chunk === '' || $chunk === false) {
                        usleep(10000);
                        continue;
                    }

                    echo $chunk;
                    @ob_flush();
                    flush();

                    $buffer .= $chunk;
                    [$buffer, $answer] = $this->consumeStreamBuffer($buffer, $answer);
                }

                if (trim($buffer) !== '') {
                    [, $answer] = $this->consumeStreamBuffer($buffer . "\n\n", $answer);
                }

                if (!blank($answer)) {
                    $this->storeAIInterrogation([
                        'chat_id' => $chatId,
                        'documents_ids' => $documentsIds,
                        'role' => 'assistant',
                        'content' => $answer,
                    ]);
                }

                echo "data: " . json_encode(['type' => 'done', 'newChat' => $newChat, 'chatId' => $chatId]) . "\n\n";

                @ob_flush();
                flush();
            } catch (\Throwable $e) {
                $errorEvent = json_encode([
                    'type' => 'error',
                    'message' => 'Stream interrupted: ' . $e->getMessage(),
                ]);
                echo "data: {$errorEvent}\n\n";
                @ob_flush();
                flush();
            }
        };

        return new StreamedResponse($streamCallback, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    public function delete(Request $request)
    {
        $request->validate([
            'chat_id' => ['required', 'string'],
        ]);

        $deletedChats = AIInterrogationChat::query()
            ->where('_id', $request['chat_id'])
            ->where('user_id', $this->userId)
            ->delete();

        if ($deletedChats > 0) {
            AIInterrogation::query()
                ->where('chat_id', $request['chat_id'])
                ->delete();
        }

        return redirect()->route('interrogations.index')
            ->with('success', 'Chat has been deleted.');
    }

    public function deleteAll()
    {
        $chatIds = AIInterrogationChat::query()
            ->where('user_id', $this->userId)
            ->get(['_id'])
            ->map(fn (AIInterrogationChat $chat) => (string) $chat->_id)
            ->all();

        AIInterrogationChat::query()
            ->where('user_id', $this->userId)
            ->delete();

        if (!empty($chatIds)) {
            AIInterrogation::query()
                ->whereIn('chat_id', $chatIds)
                ->delete();
        }

        return redirect()->route('interrogations.index')
            ->with('success', 'All AI Interrogation chats have been deleted.');
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

        return AIInterrogationChat::query()
            ->where('user_id', $this->userId)
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get(['_id', 'title', 'created_at', 'updated_at'])
            ->map(fn (AIInterrogationChat $chat) => [
                'chat_id' => (string) $chat->_id,
                'title' => $chat->title ?: 'Untitled chat',
                'document_count' => $this->getChatDocumentCount((string) $chat->_id),
                'updated_at' => $chat->updated_at ?? $chat->created_at,
            ]);
    }

    private function userOwnsDocuments(array $documentsIds): bool
    {
        if (blank($this->userId) || empty($documentsIds)) {
            return false;
        }

        $objectIds = [];

        foreach ($documentsIds as $documentId) {
            if (!preg_match('/^[a-f0-9]{24}$/i', $documentId)) {
                return false;
            }

            $objectIds[] = new ObjectId($documentId);
        }

        $ownedCount = Upload::query()
            ->whereIn('_id', $objectIds)
            ->where('user_id', $this->userId)
            ->whereNull('deleted_at')
            ->count();

        return $ownedCount === count($documentsIds);
    }

    private function createNewChat(): string
    {
        $chat = AIInterrogationChat::create([
            'user_id' => $this->userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (string) $chat->_id;
    }

    private function existsChat(string $chatId): bool
    {
        return AIInterrogationChat::query()
            ->where('_id', $chatId)
            ->where('user_id', $this->userId)
            ->exists();
    }

    private function getActiveChatId(): ?string
    {
        if (blank($this->interrogationId)) {
            return null;
        }

        return $this->existsChat($this->interrogationId)
            ? $this->interrogationId
            : null;
    }

    private function storeAIInterrogation(array $data): void
    {
        AIInterrogation::create([
            'chat_id' => $data['chat_id'],
            'documents_ids' => $data['documents_ids'],
            'role' => $data['role'] ?? 'user',
            'content' => $data['content'],
            'created_at' => now(),
        ]);

        AIInterrogationChat::query()
            ->where('_id', $data['chat_id'])
            ->where('user_id', $this->userId)
            ->update(['updated_at' => now()]);
    }

    private function getHistoryQuery(string $chatId): array
    {
        $interrogations = AIInterrogation::query()
            ->where('chat_id', $chatId)
            ->orderBy('_id', 'asc')
            ->get();

        $history = [];
        foreach ($interrogations as $interrogation) {
            $history[] = [
                'role' => $interrogation->role,
                'content' => $interrogation->content,
                'at' => $interrogation->created_at,
            ];
        }

        return $history;
    }

    private function getChatDocumentCount(string $chatId): int
    {
        return count($this->getChatDocumentsIds($chatId));
    }

    private function getChatDocumentsIds(string $chatId): array
    {
        $interrogation = AIInterrogation::query()
            ->where('chat_id', $chatId)
            ->whereNotNull('documents_ids')
            ->orderBy('_id', 'desc')
            ->first(['documents_ids']);

        return $interrogation?->documents_ids ?? [];
    }

    private function consumeStreamBuffer(string $buffer, string $answer): array
    {
        while (($pos = strpos($buffer, "\n\n")) !== false) {
            $rawEvent = substr($buffer, 0, $pos);
            $buffer = substr($buffer, $pos + 2);

            $line = trim($rawEvent);
            if (!str_starts_with($line, 'data:')) {
                continue;
            }

            $jsonPayload = trim(substr($line, 5));
            $payload = json_decode($jsonPayload, true);

            if (!is_array($payload)) {
                continue;
            }

            $type = $payload['type'] ?? null;

            if ($type === 'chunk' && isset($payload['delta'])) {
                $answer .= (string) $payload['delta'];
            } elseif ($type === 'done' && isset($payload['answer'])) {
                $answer = (string) $payload['answer'];
            }
        }

        return [$buffer, $answer];
    }
}
