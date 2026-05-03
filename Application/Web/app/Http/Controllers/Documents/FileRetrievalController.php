<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Upload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use MongoDB\BSON\ObjectId;

class FileRetrievalController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:500'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ]);

        $userId = optional($request->user())->getKey();

        if (blank($userId)) {
            abort(401);
        }

        $response = Http::timeout(60)
            ->connectTimeout(10)
            ->post(config('mcp.host') . ':' . config('mcp.port') . config('mcp.vector_search_endpoint'), [
                'user_id' => (string) $userId,
                'query' => $validated['query'],
                'max_num_results' => $validated['limit'] ?? 10,
            ]);

        if ($response->failed()) {
            return response()->json([
                'message' => 'Failed to search documents.',
            ], 502);
        }

        $payload = $response->json();

        if (!is_array($payload)) {
            return response()->json([
                'message' => 'Invalid search response.',
            ], 502);
        }

        return response()->json([
            'query' => $payload['search_query'] ?? $validated['query'],
            'files' => $this->resolveFiles($payload['results'] ?? [], (string) $userId),
        ]);
    }

    private function resolveFiles(array $results, string $userId): array
    {
        $documentIds = [];
        $vectorFileIds = [];

        foreach ($results as $result) {
            if (!is_array($result)) {
                continue;
            }

            if (!blank($result['document_id'] ?? null)) {
                $documentIds[] = (string) $result['document_id'];
            }

            if (!blank($result['file_id'] ?? null)) {
                $vectorFileIds[] = (string) $result['file_id'];
            }
        }

        if (empty($documentIds) && empty($vectorFileIds)) {
            return [];
        }

        $objectIds = collect($documentIds)
            ->filter(fn (string $documentId) => preg_match('/^[a-f0-9]{24}$/i', $documentId))
            ->map(fn (string $documentId) => new ObjectId($documentId))
            ->values()
            ->all();

        $uploads = Upload::query()
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->where(function ($query) use ($objectIds, $vectorFileIds) {
                if (!empty($objectIds)) {
                    $query->orWhereIn('_id', $objectIds);
                }

                if (!empty($vectorFileIds)) {
                    $query->orWhereIn('vector_file_id', $vectorFileIds);
                }
            })
            ->get(['_id', 'original_name', 'mime_type', 'size', 'status', 'vector_file_id', 'created_at']);

        $uploadsById = $uploads->keyBy(fn (Upload $upload) => (string) $upload->_id);
        $uploadsByVectorFileId = $uploads->keyBy(fn (Upload $upload) => (string) $upload->vector_file_id);
        $files = [];
        $seen = [];

        foreach ($results as $result) {
            if (!is_array($result)) {
                continue;
            }

            $upload = $uploadsById->get((string) ($result['document_id'] ?? ''))
                ?? $uploadsByVectorFileId->get((string) ($result['file_id'] ?? ''));

            if (!$upload) {
                continue;
            }

            $documentId = (string) $upload->_id;
            if (isset($seen[$documentId])) {
                continue;
            }

            $files[] = [
                '_id' => $documentId,
                'original_name' => (string) $upload->original_name,
                'mime_type' => (string) $upload->mime_type,
                'size' => (int) $upload->size,
                'status' => (string) $upload->status,
                'score' => $result['score'] ?? null,
                'snippet' => $this->snippet($result['content'] ?? []),
                'created_at' => $upload->created_at,
            ];
            $seen[$documentId] = true;
        }

        return $files;
    }

    private function snippet(array $content): string
    {
        $parts = [];

        foreach ($content as $chunk) {
            if (!is_array($chunk) || blank($chunk['text'] ?? null)) {
                continue;
            }

            $parts[] = trim((string) $chunk['text']);
        }

        return mb_strimwidth(implode(' ', $parts), 0, 220, '...');
    }
}
