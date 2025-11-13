<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Interrogation;
use App\Models\Upload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;
use MongoDB\BSON\ObjectId;

class InterrogateDocumentController extends Controller
{
    /**
     * GET: Show the document interrogation view.
     */
    public function index(Request $request): Response
    {
        $id = (string) $request->query('id', '');
        $userId = optional($request->user())->getKey();

        if ($id === '' || !$userId) {
            abort(404);
        }

        $upload = null;
        if (preg_match('/^[a-f0-9]{24}$/i', $id)) {
            $oid = new ObjectId($id);
            $upload = Upload::query()
                ->where('_id', $oid)
                ->where('user_id', $userId)
                ->first();
        }

        if (!$upload) {
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
    public function store(Request $request)
    {
        $validated = $request->validate([
            'document_id' => ['required', 'string'],
            'query' => ['required', 'string', 'max:5000'],
        ]);

        $payload = [
            'document_id' => $validated['document_id'],
            'question'    => $validated['query'],
            'extra'       => null
        ];

        $this->storeDocumentInterrogation([
            'document_id' => $validated['document_id'],
            'role'        => 'user',
            'content'     => $validated['query']
        ]);

        // make a POST request to the MCP server
        $response = Http::post(config('mcp.host') . ':' . config('mcp.port') . config('mcp.query_endpoint'), $payload);

        if ($response->failed()) {
            return response()->json([
                'error' => 'Failed to communicate with MCP server.',
            ], 500);
        }

        $data = $response->json();

        $this->storeDocumentInterrogation([
            'document_id' => $validated['document_id'],
            'role'        => 'assistant',
            'content'     => $data['answer'] ?? ''
        ]);

        return response()->json([
            'answer' => $data['answer'] ?? '',
        ]);
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
