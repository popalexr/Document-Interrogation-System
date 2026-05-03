<?php

namespace App\Http\Controllers\Miscellaneous;

use App\Http\Controllers\Controller;
use App\Models\Upload;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class FavoriteDocumentsController extends Controller
{
    private ?string $userId;

    public function __construct(private Request $request)
    {
        $this->userId = optional($request->user())->getKey();
    }

    public function __invoke(): Response
    {
        return Inertia::render('documents/FavoriteDocuments', [
            'favoriteDocuments' => $this->getFavoriteDocuments(),
        ]);
    }

    private function getFavoriteDocuments(): Collection
    {
        if (blank($this->userId)) {
            return collect();
        }

        return Upload::query()
            ->where('user_id', $this->userId)
            ->where('favorite', true)
            ->whereNull('deleted_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get([
                '_id',
                'original_name',
                'mime_type',
                'size',
                'status',
                'r2_key',
                'favorite',
                'created_at',
                'updated_at',
            ])
            ->map(function (Upload $upload) {
                return [
                    '_id' => (string) $upload->_id,
                    'original_name' => $upload->original_name,
                    'mime_type' => $upload->mime_type,
                    'size' => (int) $upload->size,
                    'status' => (string) $upload->status,
                    'r2_key' => (string) $upload->r2_key,
                    'favorite' => (bool) $upload->favorite,
                    'created_at' => $upload->created_at,
                    'updated_at' => $upload->updated_at,
                ];
            });
    }
}
