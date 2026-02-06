<?php

namespace App\Http\Controllers\Files;

use App\Http\Controllers\Controller;
use App\Models\Upload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DownloadFileController extends Controller
{
    private ?Upload $file;

    private $storage;

    public function __construct(private Request $request)
    {
        $fileId = $this->request->get('id', null);
        $this->file = $fileId ? Upload::find($fileId) : null;

        $this->storage = Storage::disk('r2');
    }

    public function __invoke()
    {
        if (blank($this->file)) {
            return redirect()->back()->with('error', 'File not found.');
        }

        $this->storage = Storage::disk('r2');
        $path = $this->file->r2_key;

        if (blank($path)) {
            return redirect()->back()->with('error', 'File path is invalid.');
        }

        if (! $this->storage->exists($path)) {
            return redirect()->back()->with('error', 'File does not exist in storage.');
        }

        $size = $this->getFileSize($path);

        $headers = $this->getHeader();

        if (! is_null($size)) {
            $headers['Content-Length'] = (string) $size;
        }

        $stream = $this->storage->readStream($path);

        if ($stream === false) {
            return redirect()->back()->with('error', 'Failed to read the file from storage.');
        }

        return response()->streamDownload(function () use ($stream) {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, $this->file->original_name, $headers);
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
            'Content-Disposition'   => 'attachment; filename="'.$this->file->original_name.'"',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }
}
