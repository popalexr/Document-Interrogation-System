<?php

namespace App\Http\Controllers\Collabora;

use App\Http\Controllers\Collabora\Concerns\HandlesWopiFiles;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileContentsController extends Controller
{
    use HandlesWopiFiles;

    public function __invoke(Request $request, string $file): StreamedResponse
    {
        [$resolvedFile, $storage, $path] = $this->resolveFileFromToken(
            $file,
            (string) $request->query('access_token'),
        );

        $stream = $storage->readStream($path);
        abort_if($stream === false, 404, 'Error while reading file.');

        $headers = [
            'Content-Type' => $this->fileMimeType($storage, $path, $resolvedFile),
            'Content-Disposition' => 'inline; filename="'.$resolvedFile->original_name.'"',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ];

        $size = $this->fileSize($storage, $path);
        if ($size > 0) {
            $headers['Content-Length'] = (string) $size;
        }

        return response()->stream(function () use ($stream) {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, $headers);
    }
}
