<?php

namespace App\Http\Controllers\Upload;

use App\Events\Uploads\FileUpload;
use App\Http\Controllers\Controller;
use App\Http\Requests\Uploads\UploadRequest;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    private ?User $user;
    public function __construct(Request $request)
    {
        $this->user = $request->user();
    }

    public function store(UploadRequest $request)
    {
        $file = $request['file'];

        $originalName = $file->getClientOriginalName();
        $mime = $this->resolveMimeType($file);
        $size = $file->getSize();
        $checksum = hash_file('sha256', $file->getRealPath());

        $prefix = config('filesystems.disks.r2') ? env('R2_UPLOADS_PREFIX', 'uploads') : 'uploads';
        $uuid = (string) Str::uuid();
        $safeName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME));
        $extension = $file->getClientOriginalExtension();
        $key = trim($prefix, '/').'/'.$uuid.'/' . ($safeName !== '' ? $safeName : 'file');
        if ($extension) {
            $key .= '.'.$extension;
        }

        // Upload to Cloudflare R2 using the s3 driver
        $disk = Storage::disk('r2');
        $disk->put($key, fopen($file->getRealPath(), 'r'));

        $upload = $this->addFileInDatabase(
            userId: (string) $this->user->getKey(),
            originalName: $originalName,
            mimeType: $mime,
            size: $size,
            checksum: $checksum,
            r2Bucket: config('filesystems.disks.r2.bucket'),
            r2Key: $key,
            status: 'uploading',
            meta: [
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ],
        );

        FileUpload::dispatch($upload->_id);

        return response()->json([
            'id' => (string) $upload->_id,
            'status' => $upload->status,
            'r2_key' => $upload->r2_key,
            'size' => $upload->size,
            'mime_type' => $upload->mime_type,
            'original_name' => $upload->original_name,
        ], 201);
    }

    private function addFileInDatabase(
        string $userId,
        string $originalName,
        string $mimeType,
        int    $size,
        string $checksum,
        string $r2Bucket,
        string $r2Key,
        string $status,
        array  $meta = []
    ): Upload {
        return Upload::create([
            'user_id' => $userId,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'size' => $size,
            'checksum' => $checksum,
            'r2_bucket' => $r2Bucket,
            'r2_key' => $r2Key,
            'status' => $status,
            'meta' => $meta,
        ]);
    }

    private function resolveMimeType($file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        $mimeByExtension = [
            'md'       => 'text/markdown',
            'markdown' => 'text/markdown',
            'mkd'      => 'text/markdown',
        ];

        if (isset($mimeByExtension[$extension])) {
            return $mimeByExtension[$extension];
        }

        return $file->getMimeType()
            ?: $file->getClientMimeType()
            ?: 'application/octet-stream';
    }
}

