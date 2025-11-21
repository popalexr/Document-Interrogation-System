<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Interrogations\DocumentInterrogationRequest;
use App\Models\Interrogation;
use App\Models\Upload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;
use MongoDB\BSON\ObjectId;

class InterrogateDocumentController extends Controller
{
    private $userId;
    private $documentId;

    public function __construct(private Request $request)
    {
        $this->userId = optional($request->user())->getKey();
        $this->documentId = (string) $request->query('id', null);
    }

    /**
     * GET: Show the document interrogation view.
     */
    public function index(): Response
    {
        if (blank($this->documentId) || blank($this->userId)) {
            abort(404);
        }

        $upload = $this->getDocumentById();

        if (blank($upload)) {
            abort(404);
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

        $chats = $this->getHistoryQuery((string) $document['_id']);

        return Inertia::render('documents/Interrogate', [
            'document' => $document,
            'chats'    => $chats,
        ]);
    }
    /**
     * POST: Send a query to interrogate the document.
     */
    public function store(DocumentInterrogationRequest $request)
    {
        $payload = [
            'document_id' => $request['document_id'],
            'user_id'     => $this->userId,
            'question'    => $request['query'],
            'extra'       => null
        ];

        $this->storeDocumentInterrogation([
            'document_id' => $request['document_id'],
            'role'        => 'user',
            'content'     => $request['query']
        ]);

        // make a POST request to the MCP server
        $response = Http::timeout(120)
            ->connectTimeout(10)
            ->post(config('mcp.host') . ':' . config('mcp.port') . config('mcp.query_endpoint'), $payload);

        if ($response->failed()) {
            return response()->json([
                'error' => 'Failed to communicate with MCP server.',
            ], 500);
        }

        $data = $response->json();

        $this->storeDocumentInterrogation([
            'document_id' => $request['document_id'],
            'role'        => 'assistant',
            'content'     => $data['answer'] ?? ''
        ]);

        return response()->json([
            'answer' => $data['answer'] ?? '',
        ]);
    }

    private function getDocumentById()
    {
        if (preg_match('/^[a-f0-9]{24}$/i', $this->documentId)) {
            $oid = new ObjectId($this->documentId);
            return Upload::query()
                ->where('_id', $oid)
                ->where('user_id', $this->userId)
                ->first();
        }

        return null;
    }

    private function storeDocumentInterrogation(array $data): void
    {
        $payload = [
            'document_id' => $data['document_id'],
            'role'        => $data['role'] ?? 'user',
            'content'     => $data['content'],
            'created_at'  => now(),
        ];

        Interrogation::create($payload);
    }

    private function getHistoryQuery(string $documentId): array
    {
        $interrogations = Interrogation::query()
            ->where('document_id', $documentId)
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
}
