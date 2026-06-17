<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Upload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ViewFileAPIController extends Controller
{
    public function __invoke(Request $request)
    {
        $file = $this->resolveFile($request);

        if (blank($file)) {
            return abort(404, 'File not found.');
        }

        $storage = Storage::disk('r2');
        $path = $file->r2_key;

        if (blank($path)) {
            return abort(404, 'File not found.');
        }

        if (! $storage->exists($path)) {
            return abort(404, 'File not found.');
        }

        $size = $this->getFileSize($storage, $path);

        $headers = $this->getHeader($file);

        if (! is_null($size)) {
            $headers['Content-Length'] = (string) $size;
        }

        $stream = $storage->readStream($path);

        if ($stream === false) {
            return abort(404, 'Error while reading file.');
        }

        return response()->stream(function () use ($stream) {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, $headers);
    }

    private function resolveFile(Request $request): ?Upload
    {
        $fileId = $request->get('id', null);
        $userId = (string) $request->user()?->getKey();
        $file = is_string($fileId) ? Upload::query()->whereKey($fileId)->first() : null;

        return $file
            && (string) $file->user_id === $userId
            && blank($file->deleted_at)
                ? $file
                : null;
    }

    private function getFileSize($storage, string $path): ?int
    {
        try {
            return $storage->size($path);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getHeader(Upload $file): array
    {
        return [
            'Content-Type'          => $file->mime_type ?? 'application/octet-stream',
            'Content-Disposition'   => 'inline; filename="'.$file->original_name.'"',
            'Cache-Control'         => 'public, max-age=3600',
        ];
    }
}
