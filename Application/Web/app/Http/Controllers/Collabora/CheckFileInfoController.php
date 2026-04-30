<?php

namespace App\Http\Controllers\Collabora;

use App\Http\Controllers\Collabora\Concerns\HandlesWopiFiles;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckFileInfoController extends Controller
{
    use HandlesWopiFiles;

    public function __invoke(Request $request, string $file): JsonResponse
    {
        [$resolvedFile, $storage, $path] = $this->resolveFileFromToken(
            $file,
            (string) $request->query('access_token'),
        );

        $updatedAt = $resolvedFile->updated_at ?? $resolvedFile->created_at ?? now();

        return response()->json([
            'BaseFileName' => (string) $resolvedFile->original_name,
            'BreadcrumbDocName' => (string) $resolvedFile->original_name,
            'DisablePrint' => false,
            'DisableExport' => false,
            'HidePrintOption' => false,
            'HideSaveOption' => true,
            'LastModifiedTime' => $updatedAt->toIso8601String(),
            'OwnerId' => (string) $resolvedFile->user_id,
            'ReadOnly' => true,
            'Size' => $this->fileSize($storage, $path),
            'SupportsGetLock' => false,
            'SupportsLocks' => false,
            'SupportsUpdate' => false,
            'UserCanNotWriteRelative' => true,
            'UserCanWrite' => false,
            'UserFriendlyName' => 'Viewer',
            'UserId' => (string) $resolvedFile->user_id,
            'Version' => sha1(implode('|', [
                (string) $resolvedFile->getKey(),
                (string) $resolvedFile->r2_key,
                (string) optional($updatedAt)->timestamp,
            ])),
        ]);
    }
}
