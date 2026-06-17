<?php

namespace App\Repositories;

use App\Models\Upload;
use Illuminate\Support\Collection;

class UploadRepository
{
    public function dashboardUploadsForUser(string $userId): Collection
    {
        return Upload::query()
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
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
            ->map(function ($u) {
                return [
                    '_id' => (string) $u->_id,
                    'original_name' => $u->original_name,
                    'mime_type' => $u->mime_type,
                    'size' => (int) $u->size,
                    'status' => (string) $u->status,
                    'r2_key' => (string) $u->r2_key,
                    'favorite' => (bool) $u->favorite,
                    'created_at' => $u->created_at,
                    'updated_at' => $u->updated_at,
                ];
            });
    }
}
