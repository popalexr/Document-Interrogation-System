<?php

namespace App\Http\Controllers\Collabora\Concerns;

use App\Models\Edit;
use App\Models\Upload;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

trait HandlesWopiFiles
{
    private array $collaboraExtensions = [
        'doc',
        'docx',
        'odt',
        'rtf',
        'ppt',
        'pptx',
        'odp',
        'xls',
        'xlsx',
        'ods',
        'csv',
    ];

    private function resolveFileForUser(string $fileId, string $userId, string $source = 'upload'): Upload|Edit
    {
        $file = $source === 'edit'
            ? Edit::find($fileId)
            : Upload::find($fileId);

        abort_if(blank($file), 404, 'File not found.');
        abort_if((string) $file->user_id !== $userId, 404, 'File not found.');

        if ($file instanceof Upload) {
            abort_if(! blank($file->deleted_at), 404, 'File not found.');
        }

        abort_if(! $this->isCollaboraSupported((string) $file->original_name), 415, 'File type is not supported by Collabora.');

        return $file;
    }

    private function resolveFileFromToken(string $fileId, string $accessToken): array
    {
        $payload = $this->decryptAccessToken($accessToken);

        abort_if(($payload['file_id'] ?? null) !== $fileId, 403, 'Invalid Collabora access token.');
        abort_if(($payload['expires_at'] ?? 0) < now()->timestamp, 403, 'Expired Collabora access token.');

        $source = (string) ($payload['source'] ?? 'upload');
        $file = $this->resolveFileForUser($fileId, (string) ($payload['user_id'] ?? ''), $source);
        $storage = Storage::disk('r2');
        $path = (string) $file->r2_key;

        abort_if(blank($path), 404, 'File path is invalid.');
        abort_if(! $storage->exists($path), 404, 'File does not exist in storage.');

        return [$file, $storage, $path, $source];
    }

    private function makeAccessToken(Upload|Edit $file, string $source): string
    {
        $ttl = max(60, (int) config('services.collabora.access_token_ttl', 3600));

        return Crypt::encryptString(json_encode([
            'file_id' => (string) $file->getKey(),
            'user_id' => (string) $file->user_id,
            'source' => $source,
            'expires_at' => now()->addSeconds($ttl)->timestamp,
        ], JSON_THROW_ON_ERROR));
    }

    private function decryptAccessToken(?string $accessToken): array
    {
        abort_if(blank($accessToken), 403, 'Missing Collabora access token.');

        try {
            $payload = json_decode(Crypt::decryptString($accessToken), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            abort(403, 'Invalid Collabora access token.');
        }

        abort_if(! is_array($payload), 403, 'Invalid Collabora access token.');

        return $payload;
    }

    private function isCollaboraSupported(string $fileName): bool
    {
        return in_array(Str::lower(pathinfo($fileName, PATHINFO_EXTENSION)), $this->collaboraExtensions, true);
    }

    private function fileSize(Filesystem $storage, string $path): int
    {
        try {
            return (int) $storage->size($path);
        } catch (Throwable) {
            return 0;
        }
    }

    private function fileMimeType(Filesystem $storage, string $path, Upload|Edit $file): string
    {
        if ($file instanceof Upload && ! blank($file->mime_type)) {
            return (string) $file->mime_type;
        }

        try {
            return $storage->mimeType($path) ?: 'application/octet-stream';
        } catch (Throwable) {
            return 'application/octet-stream';
        }
    }
}
