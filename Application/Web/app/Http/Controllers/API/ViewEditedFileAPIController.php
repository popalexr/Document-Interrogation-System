<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Edit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ViewEditedFileAPIController extends Controller
{
    private ?Edit $file;

    private $storage;

    public function __construct(private Request $request)
    {
        $fileId = $this->request->get('id', null);
        $this->file = $fileId ? Edit::find($fileId) : null;

        $this->storage = Storage::disk('r2');
    }

    public function __invoke()
    {
        if (blank($this->file)) {
            return abort(404, 'File not found.');
        }

        $this->storage = Storage::disk('r2');
        $path = $this->file->r2_key;

        if (blank($path)) {
            return abort(404, 'File not found.');
        }

        if (! $this->storage->exists($path)) {
            return abort(404, 'File not found.');
        }

        $size = $this->getFileSize($path);

        $headers = $this->getHeader();

        if (! is_null($size)) {
            $headers['Content-Length'] = (string) $size;
        }

        $stream = $this->storage->readStream($path);

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

    private function getFileSize(string $path): ?int
    {
        try {
            return $this->storage->size($path);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getHeader(): array
    {
        return [
            'Content-Type'          => $this->file->mime_type ?? 'application/octet-stream',
            'Content-Disposition'   => 'inline; filename="'.$this->file->original_name.'"',
            'Cache-Control'         => 'public, max-age=3600',
        ];
    }
}
