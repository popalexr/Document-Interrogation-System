<?php

namespace App\Listeners\Uploads;

use App\Events\Uploads\FileUpload;
use App\Models\Upload;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UploadOnOpenAI implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The number of time the job may be attempted.
     */
    public $tries = 5;

    /**
     * Uploaded file object
     */
    private ?Upload $file;

    /**
     * Handle the event.
     */
    public function handle(FileUpload $event): void
    {
        $this->file = Upload::find($event->fileId);

        if ($this->file === null) {
            return;
        }

        // Dispatch to MCP Server for vectorizing the document
        $response = $this->dispatch_to_mcp($this->file);

        if ($response->failed()) {
            // Log the error or take appropriate action
            Log::error('Failed to dispatch document to MCP Server for vectorization.', [
                'file_id' => (string) $this->file->_id,
                'response' => $response->body(),
            ]);

            throw new \Exception('MCP Server vectorization request failed.');
        }

        $response = $response->json();

        if ($response['status'] !== 'vectorization_complete') {
            Log::error('MCP Server returned unexpected status for vectorization.', [
                'file_id' => (string) $this->file->_id,
                'response' => $response,
            ]);

            throw new \Exception('MCP Server vectorization did not complete successfully.');
        }

        $this->updateFileVectorizedStatus($response['vector_store_id'], $response['vector_file_id']);
    }

    private function dispatch_to_mcp(Upload $upload): Response
    {
        return Http::timeout(120)
            ->connectTimeout(10)
            ->post(config('mcp.host') . ':' . config('mcp.port') . config('mcp.vectorize_endpoint'), [
                'document_id' => (string) $upload->_id,
                'user_id' => $upload->user_id,
            ]);
    }

    private function updateFileVectorizedStatus(string $vector_store_id, string $vector_file_id): void
    {
        if ($this->file === null) {
            return;
        }

        $this->file->status = "uploaded";
        $this->file->vector_store_id = $vector_store_id;
        $this->file->vector_file_id = $vector_file_id;
        $this->file->save();
    }
}
