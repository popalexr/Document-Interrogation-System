<?php

namespace App\Http\Controllers\Files;

use App\Http\Controllers\Controller;
use App\Models\Edit;
use App\Models\Upload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DownloadFileController extends Controller
{
    public function __invoke(Request $request)
    {
        $file = $this->resolveFile($request);

        if (blank($file)) {
            return redirect()->back()->with('error', 'File not found.');
        }

        $storage = Storage::disk('r2');
        $path = $file->r2_key;

        if (blank($path)) {
            return redirect()->back()->with('error', 'File path is invalid.');
        }

        if (! $storage->exists($path)) {
            return redirect()->back()->with('error', 'File does not exist in storage.');
        }

        $size = $this->getFileSize($storage, $path);

        $headers = $this->getHeader($file);

        if (! is_null($size)) {
            $headers['Content-Length'] = (string) $size;
        }

        $stream = $storage->readStream($path);

        if ($stream === false) {
            return redirect()->back()->with('error', 'Failed to read the file from storage.');
        }

        return response()->streamDownload(function () use ($stream) {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, $file->original_name, $headers);
    }

    private function resolveFile(Request $request): Upload|Edit|null
    {
        $fileId = $request->get('id', null);
        $source = $request->get('source', 'upload');
        $userId = (string) $request->user()?->getKey();

        $file = is_string($fileId)
            ? ($source === 'edit'
                ? Edit::query()->whereKey($fileId)->first()
                : Upload::query()->whereKey($fileId)->first())
            : null;

        return $file && (string) $file->user_id === $userId
            && (! $file instanceof Upload || blank($file->deleted_at))
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

    private function getHeader(Upload|Edit $file): array
    {
        return [
            'Content-Type'          => $file->mime_type ?? 'application/octet-stream',
            'Content-Disposition'   => 'attachment; filename="'.$file->original_name.'"',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }
}
