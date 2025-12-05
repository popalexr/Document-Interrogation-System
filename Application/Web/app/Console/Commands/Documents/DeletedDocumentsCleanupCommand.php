<?php

namespace App\Console\Commands\Documents;

use App\Models\Upload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class DeletedDocumentsCleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documents:cleanup-deleted';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup deleted documents from Cloudflare R2 storage';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting cleanup of deleted documents...');

        $deletedDocuments = $this->getDeletedDocuments();

        if ($deletedDocuments->isEmpty()) {
            $this->info('No deleted documents found for cleanup.');
            return;
        }

        foreach ($deletedDocuments as $document) {
            $this->deleteDocumentFromR2($document->r2_key);
            $this->deleteDocumentFromOpenAI((string) $document->_id, (string) $document->user_id);
            $this->deleteDocumentFromCollection($document->_id);
            $this->info("Deleted document: {$document->original_name} (ID: {$document->_id})");
        }

        $this->info('Cleanup completed successfully.');
    }

    /**
     * Get all deleted documents from the database that
     * are older than the specified threshold in config: uploads.delete_after_days.
     */
    private function getDeletedDocuments() : object
    {
        return Upload::query()
            ->whereNotNull('deleted_at')
            ->where('deleted_at', '<', now()->subDays(config('uploads.delete_after_days')))
            ->get();
    }

    /**
     * Delete the specified document from Cloudflare R2 storage.
     * 
     * @param string $r2Key
     */
    private function deleteDocumentFromR2(string $r2Key): void
    {
        Storage::disk('r2')->delete($r2Key);
    }

    /**
     * Delete the specified document from collection.
     * 
     * @param string $documentId
     */
    private function deleteDocumentFromCollection(string $documentId): void
    {
        try
        {
            $document = Upload::find($documentId);

            if (!$document) {
                throw new \Exception("Document with ID: {$documentId} not found in collection.");
            }

            $document->delete();
        }
        catch (\Exception $e)
        {
            $this->error("Failed to delete document with ID: {$documentId}. Error: " . $e->getMessage());
        }
    }

    /**
     * Delete the specified document from OpenAI vector store.
     * 
     * @param string $documentId
     * @param string $userId
     */
    private function deleteDocumentFromOpenAI(string $documentId, string $userId): object
    {
        return Http::timeout(120)
            ->connectTimeout(10)
            ->delete(config('mcp.host') . ':' . config('mcp.port') . config('mcp.delete_document_endpoint'), [
                'document_id' => (string) $documentId,
                'user_id' => $userId,
            ]);
    }
}
