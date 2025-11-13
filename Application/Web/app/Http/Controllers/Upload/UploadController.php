<?php

namespace App\Http\Controllers\Upload;

use App\Http\Controllers\Controller;
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'file' => ['required', 'file'],
        ]);

        $file = $validated['file'];

        $originalName = $file->getClientOriginalName();
        $mime = $file->getClientMimeType();
        $size = $file->getSize();
        $checksum = hash_file('sha256', $file->getRealPath());

        $prefix = config('filesystems.disks.r2') ? env('R2_QUARANTINE_PREFIX', 'quarantine') : 'quarantine';
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

        $upload = Upload::create([
            'user_id' => $this->user->getKey(),
            'original_name' => $originalName,
            'mime_type' => $mime,
            'size' => $size,
            'checksum' => $checksum,
            'r2_bucket' => config('filesystems.disks.r2.bucket'),
            'r2_key' => $key,
            'status' => 'quarantine',
            'meta' => [
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ],
        ]);

        return response()->json([
            'id' => (string) $upload->_id,
            'status' => $upload->status,
            'r2_key' => $upload->r2_key,
            'size' => $upload->size,
            'mime_type' => $upload->mime_type,
            'original_name' => $upload->original_name,
        ], 201);
    }
}

