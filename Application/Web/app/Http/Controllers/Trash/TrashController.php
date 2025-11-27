<?php

namespace App\Http\Controllers\Trash;

use App\Http\Controllers\Controller;
use App\Models\Upload;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TrashController extends Controller
{
    public function __construct(private Request $request)
    {}

    /**
     * Show the trash page with deleted documents.
     */
    public function __invoke()
    {
        return Inertia::render('documents/DeletedDocuments', [
            'deletedDocuments' => $this->getDeletedDocuments(),
        ]);
    }
    
    /**
     * Get deleted documents.
     */
    private function getDeletedDocuments()
    {
        return Upload::query()
            ->whereNotNull('deleted_at')
            ->orderByDesc('deleted_at')
            ->limit(100)
            ->get(['_id', 'original_name', 'mime_type', 'size', 'status', 'r2_key', 'deleted_at'])
            ->map(function ($u) {
                return [
                    '_id' => (string) $u->_id,
                    'original_name' => $u->original_name,
                    'mime_type' => $u->mime_type,
                    'size' => (int) $u->size,
                    'status' => (string) $u->status,
                    'r2_key' => (string) $u->r2_key,
                    'deleted_at' => $u->deleted_at,
                ];
            });
    }
}
