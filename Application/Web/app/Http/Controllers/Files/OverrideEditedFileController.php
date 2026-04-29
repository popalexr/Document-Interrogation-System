<?php

namespace App\Http\Controllers\Files;

use App\Events\Uploads\FileUpload;
use App\Http\Controllers\Controller;
use App\Http\Requests\Files\OverrideEditedFileRequest;
use App\Models\Edit;
use App\Models\Upload;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use MongoDB\BSON\ObjectId;

class OverrideEditedFileController extends Controller
{
    public function __invoke(OverrideEditedFileRequest $request)
    {
        $userId = (string) $request->user()->getKey();
        $edit = $this->getEditableFile((string) $request->validated('file_id'), $userId);

        if (blank($edit)) {
            return redirect()->back()->with('error', 'The specified edited file was not found.');
        }

        $upload = Upload::query()
            ->where('_id', new ObjectId((string) $edit->original_document_id))
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->first();

        if (blank($upload)) {
            return redirect()->back()->with('error', 'The original file for this edit was not found.');
        }

        $disk = Storage::disk('r2');
        $sourceKey = (string) $edit->r2_key;

        if (! $disk->exists($sourceKey)) {
            return redirect()->back()->with('error', 'The edited file was not found in storage.');
        }

        $previousKey = (string) $upload->r2_key;
        $targetKey = $this->makeUploadKey((string) ($upload->original_name ?? $edit->original_name ?? 'file'));

        if (! $disk->copy($sourceKey, $targetKey)) {
            return redirect()->back()->with('error', 'Failed to override the file. Please try again.');
        }

        $upload->size = $disk->size($targetKey);
        $upload->checksum = $this->checksum($disk->readStream($targetKey));
        $upload->r2_bucket = config('filesystems.disks.r2.bucket');
        $upload->r2_key = $targetKey;
        $upload->status = 'uploading';
        $upload->vector_store_id = null;
        $upload->vector_file_id = null;
        $upload->meta = [
            ...(is_array($upload->meta) ? $upload->meta : []),
            'last_override' => [
                'source' => 'edited_file',
                'edit_id' => (string) $edit->_id,
                'previous_r2_key' => $previousKey,
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'overridden_at' => now()->toJSON(),
            ],
        ];
        $upload->save();

        FileUpload::dispatch($upload->_id);

        return redirect()->route('dashboard.home')->with('success', 'You overrode the file with the edited version successfully.');
    }

    private function getEditableFile(string $fileId, string $userId): ?Edit
    {
        if (! preg_match('/^[a-f0-9]{24}$/i', $fileId)) {
            return null;
        }

        return Edit::query()
            ->where('_id', new ObjectId($fileId))
            ->where('user_id', $userId)
            ->first();
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
     * @param  resource|false  $stream
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
