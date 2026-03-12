<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Interrogations\DocumentInterrogationRequest;
use App\Models\Chat;
use App\Models\Interrogation;
use App\Models\Upload;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use MongoDB\BSON\ObjectId;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InterrogateDocumentController extends Controller
{
    private $userId;
    private $documentId;
    private $chatId;

    public function __construct(private Request $request)
    {
        $this->userId = optional($request->user())->getKey();
        $this->documentId = (string) $request->query('id', null);
        $this->chatId = (string) $request->query('chat_id', null);
    }

    /**
     * GET: Show the document interrogation view.
     */
    public function index()
    {
        if (blank($this->documentId) || blank($this->userId)) {
            abort(404);
        }

        $upload = $this->getDocumentById();

        if (blank($upload)) {
            abort(404);
        }

        if (!blank($this->chatId) && !$this->existsChat($this->chatId)) {
            return redirect()->route('documents.interrogate', ['id' => $this->documentId]);
        }

        $document = [
            '_id' => (string) $upload->_id,
            'original_name' => $upload->original_name,
            'mime_type' => $upload->mime_type,
            'size' => (int) $upload->size,
            'status' => (string) $upload->status,
            'r2_key' => (string) $upload->r2_key,
            'created_at' => $upload->created_at,
        ];

        if (blank($this->chatId)) {
            $interrogations = [];
        }
        else {
            $interrogations = $this->getHistoryQuery((string) $this->chatId);
        }

        return Inertia::render('documents/Interrogate', [
            'document'          => $document,
            'interrogations'    => $interrogations,
            'chats'             => $this->getChatsList($this->documentId),
            'chat_id'           => $this->chatId ?? null,
        ]);
    }
    /**
     * POST: Send a query to interrogate the document.
     */
    public function store(DocumentInterrogationRequest $request)
    {
        $documentId = $request['document_id'];
        $chatId = $request['chat_id'] ?? null;
        $newChat = false;

        if (blank($chatId)) {
            $chatId = $this->createNewChat($documentId);
            $newChat = true;
        }

        $payload = [
            'document_id' => $documentId,
            'user_id'     => $this->userId,
            'question'    => $request['query'],
            'extra'       => [
                'history' => $this->getHistoryQuery($chatId),
            ],
        ];

        // Store user message
        $this->storeDocumentInterrogation([
            'chat_id' => $chatId,
            'role'        => 'user',
            'content'     => $request['query'],
        ]);

        // Build MCP URL
        $mcpUrl = sprintf(
            '%s:%s%s',
            config('mcp.host'),
            config('mcp.port'),
            config('mcp.query_endpoint')
        );

        // Use raw Guzzle to avoid Laravel Http client buffering
        $client = new Client([
            'timeout'      => 120,
            'connect_timeout' => 10,
            'http_errors'  => false, // don't throw on 4xx/5xx
        ]);

        try {
            $response = $client->post($mcpUrl, [
                'stream'  => true, // IMPORTANT: streaming
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
                'error'   => 'MCP server returned an error status.',
                'status'  => $status,
            ], 500);
        }

        $bodyStream = $response->getBody();

        $streamCallback = function () use ($bodyStream, $chatId, $newChat) {
            // Avoid PHP timing out while relaying a long-running SSE stream
            @set_time_limit(0);
            @ini_set('max_execution_time', '0');
            // Disable PHP output buffering and compression so SSE can flush
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
                        usleep(10000); // 10ms
                        continue;
                    }

                    // The MCP endpoint is already sending SSE ("data: {...}\n\n"),
                    // so we just proxy the raw chunk as-is:
                    echo $chunk;
                    @ob_flush();
                    flush();

                    $buffer .= $chunk;
                    [$buffer, $answer] = $this->consumeStreamBuffer($buffer, $answer);
                }

                // Flush any leftover buffer through your parser
                if (trim($buffer) !== '') {
                    [, $answer] = $this->consumeStreamBuffer($buffer . "\n\n", $answer);
                }

                // Persist assistant final answer if we have one
                if (!blank($answer)) {
                    $this->storeDocumentInterrogation([
                        'chat_id'     => $chatId,
                        'role'        => 'assistant',
                        'content'     => $answer,
                    ]);
                }

                // Send a final SSE event to signal completion to the client
                echo "data: " . json_encode(['type' => 'done', 'newChat' => $newChat, 'chatId' => $chatId]) . "\n\n";
                
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


    private function getDocumentById()
    {
        if (preg_match('/^[a-f0-9]{24}$/i', $this->documentId)) {
            $oid = new ObjectId($this->documentId);
            return Upload::query()
                ->where('_id', $oid)
                ->where('user_id', $this->userId)
                ->whereNull('deleted_at')
                ->first();
        }

        return null;
    }

    private function storeDocumentInterrogation(array $data): void
    {
        $payload = [
            'chat_id'     => $data['chat_id'],
            'role'        => $data['role'] ?? 'user',
            'content'     => $data['content'],
            'created_at'  => now(),
        ];

        if (!blank($this->userId)) {
            $payload['user_id'] = $this->userId;
        }

        Interrogation::create($payload);
    }

    private function getHistoryQuery(string $chatId): array
    {
        $interrogations = Interrogation::query()
            ->where('chat_id', $chatId)
            ->orderBy('_id', 'asc')
            ->get();

        $history = [];
        foreach ($interrogations as $interrogation) {
            $history[] = [
                'role'    => $interrogation->role,
                'content' => $interrogation->content,
                'at'      => $interrogation->created_at,
            ];
        }

        return $history;
    }

    private function existsChat(string $chatId): bool
    {
        return Chat::query()
            ->where('_id', $chatId)
            ->exists();
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

    private function createNewChat(string $documentId): string
    {
        $chat = Chat::create([
            'document_id' => $documentId,
            'user_id'     => $this->userId,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return (string) $chat->_id;
    }

    private function getChatsList(string $documentId): array
    {
        $chats = Chat::query()
            ->where('document_id', $documentId)
            ->orderBy('created_at', 'desc')
            ->get();

        $chatsList = [];
        foreach ($chats as $chat) {
            $chatsList[] = [
                'chat_id' => (string) $chat->_id,
                'title'   => $chat->title ?? null,
            ];
        }

        return $chatsList;
    }
}
