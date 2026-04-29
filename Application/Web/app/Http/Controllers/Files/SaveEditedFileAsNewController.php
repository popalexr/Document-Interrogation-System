<?php

namespace App\Http\Controllers\Files;

use App\Events\Uploads\FileUpload;
use App\Http\Controllers\Controller;
use App\Http\Requests\Files\SaveEditedFileAsNewRequest;
use App\Models\Edit;
use App\Models\Upload;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use MongoDB\BSON\ObjectId;

class SaveEditedFileAsNewController extends Controller
{
    public function __invoke(SaveEditedFileAsNewRequest $request)
    {
        $userId = (string) $request->user()->getKey();
        $edit = $this->getEditableFile((string) $request->validated('file_id'), $userId);

        if (blank($edit)) {
            return redirect()->back()->with('error', 'The specified edited file was not found.');
        }

        $sourceUpload = Upload::query()
            ->where('_id', new ObjectId((string) $edit->original_document_id))
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->first();

        if (blank($sourceUpload)) {
            return redirect()->back()->with('error', 'The original file for this edit was not found.');
        }

        $disk = Storage::disk('r2');
        $sourceKey = (string) $edit->r2_key;

        if (!$disk->exists($sourceKey)) {
            return redirect()->back()->with('error', 'The edited file was not found in storage.');
        }

        $originalName = $this->resolveOriginalName(
            requestedName: (string) $request->validated('name'),
            fallbackName: (string) ($edit->original_name ?? $sourceUpload->original_name),
        );
        $targetKey = $this->makeUploadKey($originalName);

        if (!$disk->copy($sourceKey, $targetKey)) {
            return redirect()->back()->with('error', 'Failed to save the edited file. Please try again.');
        }

        $upload = Upload::create([
            'user_id' => $userId,
            'original_name' => $originalName,
            'mime_type' => (string) ($sourceUpload->mime_type ?? 'application/octet-stream'),
            'size' => $disk->size($targetKey),
            'checksum' => $this->checksum($disk->readStream($targetKey)),
            'r2_bucket' => config('filesystems.disks.r2.bucket'),
            'r2_key' => $targetKey,
            'status' => 'uploading',
            'meta' => [
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'source' => 'edited_file',
                'edit_id' => (string) $edit->_id,
                'original_document_id' => (string) $sourceUpload->_id,
            ],
        ]);

        FileUpload::dispatch($upload->_id);

        return redirect()->route('dashboard.home')->with('success', 'You saved the edited file as a new upload successfully.');
    }

    private function getEditableFile(string $fileId, string $userId): ?Edit
    {
        if (!preg_match('/^[a-f0-9]{24}$/i', $fileId)) {
            return null;
        }

        return Edit::query()
            ->where('_id', new ObjectId($fileId))
            ->where('user_id', $userId)
            ->first();
    }

    private function resolveOriginalName(string $requestedName, string $fallbackName): string
    {
        $name = trim($requestedName);
        $fallbackExtension = pathinfo($fallbackName, PATHINFO_EXTENSION);

        if ($fallbackExtension !== '' && pathinfo($name, PATHINFO_EXTENSION) === '') {
            return $name.'.'.$fallbackExtension;
        }

        return $name;
    }

    private function makeUploadKey(string $originalName): string
    {
        $prefix = config('filesystems.disks.r2') ? env('R2_UPLOADS_PREFIX', 'uploads') : 'uploads';
        $uuid = (string) Str::uuid();
        $safeName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME));
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);

        $key = trim($prefix, '/').'/'.$uuid.'/'.($safeName !== '' ? $safeName : 'file');
        if ($extension !== '') {
            $key .= '.'.$extension;
        }

        return $key;
    }

    /**
     * @param resource|false $stream
     */
    private function checksum($stream): string
    {
        if ($stream === false) {
            abort(500, 'Unable to read copied file.');
        }

        $context = hash_init('sha256');
        hash_update_stream($context, $stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        return hash_final($context);
    }
}
