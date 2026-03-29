<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Edits\EditDocumentRequest;
use App\Models\EditChat;
use App\Models\EditInterrogation;
use App\Models\Upload;
use GuzzleHttp\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use MongoDB\BSON\ObjectId;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EditDocumentController extends Controller
{
    private ?string $userId = null;
    private ?string $documentId = null;
    private ?string $chatId = null;
    private ?Upload $document = null;

    public function __construct(private Request $request)
    {
        $this->userId = optional($this->request->user())->getKey();
        $this->documentId = (string) $this->request->query('id', null);
        $this->chatId = (string) $this->request->query('chat_id', null);
        $this->document = $this->getDocumentById($this->documentId);
    }

    public function __invoke(): Response|RedirectResponse
    {
        if (blank($this->document)) {
            return redirect()->back()->with('error', 'Document not found.');
        }

        if (!blank($this->chatId) && !$this->existsChat($this->chatId, $this->documentId)) {
            return redirect()->route('documents.edit', ['id' => $this->documentId]);
        }

        return Inertia::render('documents/EditDocument', [
            'document' => $this->document,
            'chatData' => $this->getEditChatData($this->chatId),
            'chats'    => $this->getChatsList($this->documentId),
            'chat_id'  => $this->chatId ?? null,
        ]);
    }

    public function store(EditDocumentRequest $request): StreamedResponse|\Illuminate\Http\JsonResponse
    {
        $documentId = (string) $request['document_id'];
        $chatId = $request['chat_id'] ?? null;
        $newChat = false;

        if (blank($this->userId) || blank($this->getDocumentById($documentId))) {
            abort(404);
        }

        if (blank($chatId)) {
            $chatId = $this->createNewChat($documentId);
            $newChat = true;
        } elseif (!$this->existsChat($chatId, $documentId)) {
            return response()->json([
                'error' => 'Edit chat not found.',
            ], 404);
        }

        $payload = [
            'document_id' => $documentId,
            'user_id'     => $this->userId,
            'prompt'      => $request['query'],
            'extra'       => [
                'history' => $this->getHistoryQuery($chatId),
            ],
        ];

        $this->storeEditInterrogation([
            'chat_id' => $chatId,
            'role'    => 'user',
            'content' => $request['query'],
        ]);

        $mcpUrl = sprintf(
            '%s:%s%s',
            config('mcp.host'),
            config('mcp.port'),
            config('mcp.edit_document_endpoint')
        );

        $client = new Client([
            'timeout'         => 120,
            'connect_timeout' => 10,
            'http_errors'     => false,
        ]);

        try {
            $response = $client->post($mcpUrl, [
                'stream'  => true,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept'       => 'text/event-stream',
                ],
                'body'    => json_encode($payload),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error'   => 'Failed to communicate with MCP server.',
                'message' => $e->getMessage(),
            ], 500);
        }

        $status = $response->getStatusCode();
        if ($status >= 400) {
            return response()->json([
                'error'  => 'MCP server returned an error status.',
                'status' => $status,
            ], 500);
        }

        $bodyStream = $response->getBody();

        $streamCallback = function () use ($bodyStream, $chatId, $newChat) {
            @set_time_limit(0);
            @ini_set('max_execution_time', '0');
            @ini_set('output_buffering', '0');
            @ini_set('zlib.output_compression', '0');
            while (ob_get_level() > 0) { @ob_end_flush(); }
            ob_implicit_flush(true);

            $buffer = '';
            $assistantInterrogation = null;
            $assistantPayload = [
                'chat_id'          => $chatId,
                'role'             => 'assistant',
                'reasoning'        => null,
                'content'          => null,
                'edit_document_id' => null,
            ];

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

                    $buffer .= $chunk;

                    [$buffer, $assistantInterrogation, $assistantPayload, $events] = $this->consumeEditStreamBuffer(
                        $buffer,
                        $assistantInterrogation,
                        $assistantPayload
                    );

                    foreach ($events as $event) {
                        echo 'data: ' . json_encode($event) . "\n\n";
                        @ob_flush();
                        flush();
                    }
                }

                if (trim($buffer) !== '') {
                    [, $assistantInterrogation, $assistantPayload, $events] = $this->consumeEditStreamBuffer(
                        $buffer . "\n\n",
                        $assistantInterrogation,
                        $assistantPayload
                    );

                    foreach ($events as $event) {
                        echo 'data: ' . json_encode($event) . "\n\n";
                        @ob_flush();
                        flush();
                    }
                }

                echo 'data: ' . json_encode([
                    'type'           => 'done',
                    'newChat'        => $newChat,
                    'chatId'         => $chatId,
                    'editDocumentId' => $assistantPayload['edit_document_id'],
                ]) . "\n\n";
                @ob_flush();
                flush();
            } catch (\Throwable $e) {
                $errorEvent = json_encode([
                    'type'    => 'error',
                    'message' => 'Stream interrupted: ' . $e->getMessage(),
                ]);

                echo "data: {$errorEvent}\n\n";
                @ob_flush();
                flush();
            }
        };

        return new StreamedResponse($streamCallback, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
    }

    private function getEditChatData(?string $chatId = null): array
    {
        $data = [
            'id'       => $chatId,
            'title'    => null,
            'messages' => [],
        ];

        if (blank($chatId)) {
            return $data;
        }

        $chat = EditChat::query()
            ->where('_id', $chatId)
            ->where('document_id', $this->documentId)
            ->where('user_id', $this->userId)
            ->first();
        if (blank($chat)) {
            return $data;
        }

        $data['id'] = (string) $chat->_id;
        $data['title'] = $chat->title ?? null;

        $messages = EditInterrogation::query()
            ->where('chat_id', (string) $chat->_id)
            ->orderBy('_id', 'asc')
            ->get();

        foreach ($messages as $message) {
            $data['messages'][] = [
                'role'             => $message->role,
                'content'          => $message->content,
                'reasoning'        => $message->reasoning,
                'edit_document_id' => $message->edit_document_id,
                'at'               => $message->created_at,
            ];
        }

        return $data;
    }

    private function getDocumentById(?string $documentId): ?Upload
    {
        if (blank($documentId) || blank($this->userId) || !preg_match('/^[a-f0-9]{24}$/i', $documentId)) {
            return null;
        }

        return Upload::query()
            ->where('_id', new ObjectId($documentId))
            ->where('user_id', $this->userId)
            ->whereNull('deleted_at')
            ->first();
    }

    private function createNewChat(string $documentId): string
    {
        $chat = EditChat::create([
            'document_id' => $documentId,
            'user_id'     => $this->userId,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return (string) $chat->_id;
    }

    private function existsChat(string $chatId, string $documentId): bool
    {
        return EditChat::query()
            ->where('_id', $chatId)
            ->where('document_id', $documentId)
            ->where('user_id', $this->userId)
            ->exists();
    }

    private function getHistoryQuery(string $chatId): array
    {
        $interrogations = EditInterrogation::query()
            ->where('chat_id', $chatId)
            ->orderBy('_id', 'asc')
            ->get();

        $history = [];
        foreach ($interrogations as $interrogation) {
            $history[] = [
                'role'             => $interrogation->role,
                'content'          => $interrogation->content,
                'reasoning'        => $interrogation->reasoning,
                'edit_document_id' => $interrogation->edit_document_id,
                'at'               => $interrogation->created_at,
            ];
        }

        return $history;
    }

    private function storeEditInterrogation(array $data): EditInterrogation
    {
        $interrogation = EditInterrogation::create([
            'chat_id'          => $data['chat_id'],
            'role'             => $data['role'] ?? 'user',
            'reasoning'        => $data['reasoning'] ?? null,
            'content'          => $data['content'] ?? null,
            'edit_document_id' => $data['edit_document_id'] ?? null,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $this->touchChat($data['chat_id']);

        return $interrogation;
    }

    private function upsertAssistantInterrogation(?EditInterrogation $interrogation, array $data): EditInterrogation
    {
        $payload = [
            'chat_id'    => $data['chat_id'],
            'role'       => 'assistant',
            'updated_at' => now(),
        ];

        if (!blank($data['reasoning'] ?? null)) {
            $payload['reasoning'] = $data['reasoning'];
        }

        if (!blank($data['content'] ?? null)) {
            $payload['content'] = $data['content'];
        }

        if (!blank($data['edit_document_id'] ?? null)) {
            $payload['edit_document_id'] = $data['edit_document_id'];
        }

        if (blank($interrogation)) {
            $payload['created_at'] = now();
            $interrogation = EditInterrogation::create($payload);
            $this->touchChat($data['chat_id']);

            return $interrogation;
        }

        $interrogation->fill($payload);
        $interrogation->save();

        $this->touchChat($data['chat_id']);

        return $interrogation;
    }

    private function touchChat(string $chatId): void
    {
        EditChat::query()
            ->where('_id', $chatId)
            ->where('user_id', $this->userId)
            ->update([
                'updated_at' => now(),
            ]);
    }

    private function getChatsList(string $documentId): array
    {
        $chats = EditChat::query()
            ->where('document_id', $documentId)
            ->where('user_id', $this->userId)
            ->orderBy('updated_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        $chatsList = [];
        foreach ($chats as $chat) {
            $chatsList[] = [
                'chat_id'    => (string) $chat->_id,
                'title'      => $chat->title ?? null,
                'created_at' => $chat->created_at,
                'updated_at' => $chat->updated_at,
            ];
        }

        return $chatsList;
    }

    private function consumeEditStreamBuffer(
        string $buffer,
        ?EditInterrogation $assistantInterrogation,
        array $assistantPayload
    ): array {
        $events = [];

        while (preg_match("/^(.*?)(\r?\n\r?\n)/s", $buffer, $matches)) {
            $rawEvent = $matches[1];
            $buffer = substr($buffer, strlen($matches[0]));

            $dataLines = [];
            foreach (preg_split("/\r?\n/", trim($rawEvent)) as $line) {
                if (str_starts_with($line, 'data:')) {
                    $dataLines[] = ltrim(substr($line, 5));
                }
            }

            if ($dataLines === []) {
                continue;
            }

            $payload = json_decode(implode("\n", $dataLines), true);
            if (!is_array($payload)) {
                continue;
            }

            $type = $payload['type'] ?? null;

            if ($type === 'edit_prompt') {
                $reasoning = $this->extractEditPromptReasoning($payload);
                if (!blank($reasoning)) {
                    $assistantPayload['reasoning'] = $reasoning;
                    $assistantInterrogation = $this->upsertAssistantInterrogation($assistantInterrogation, $assistantPayload);
                }

                $events[] = $payload;
                continue;
            }

            if ($type === 'edit_code') {
                continue;
            }

            if ($type === 'execution_result') {
                if (($payload['status'] ?? null) === 'ok' && !blank($payload['document_id'] ?? null)) {
                    $assistantPayload['edit_document_id'] = (string) $payload['document_id'];
                    $assistantInterrogation = $this->upsertAssistantInterrogation($assistantInterrogation, $assistantPayload);
                }

                $events[] = $payload;
                continue;
            }

            if ($type === 'final_message') {
                $content = $this->extractFinalMessageContent($payload);
                if (!blank($content)) {
                    $assistantPayload['content'] = $content;
                    $assistantInterrogation = $this->upsertAssistantInterrogation($assistantInterrogation, $assistantPayload);
                }

                $events[] = $payload;
                continue;
            }

            $events[] = $payload;
        }

        return [$buffer, $assistantInterrogation, $assistantPayload, $events];
    }

    private function extractEditPromptReasoning(array $payload): ?string
    {
        $message = $payload['message'] ?? null;

        if (is_string($message)) {
            return $message;
        }

        if (is_array($message)) {
            if (is_string($message['prompt'] ?? null)) {
                return $message['prompt'];
            }

            $encoded = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return $encoded === false ? null : $encoded;
        }

        return null;
    }

    private function extractFinalMessageContent(array $payload): ?string
    {
        if (is_string($payload['content'] ?? null)) {
            return $payload['content'];
        }

        $message = $payload['message'] ?? null;

        if (is_string($message)) {
            return $message;
        }

        if (is_array($message)) {
            $encoded = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return $encoded === false ? null : $encoded;
        }

        return null;
    }
}
