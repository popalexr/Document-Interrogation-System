<?php

namespace Tests\Feature\Documents;

use App\Models\Upload;
use Tests\TestCase;

class DocumentActionsTest extends TestCase
{
    public function test_authenticated_users_can_delete_their_own_documents(): void
    {
        $user = $this->makeMongoUser(['_id' => 'document-owner-id']);
        $document = $this->createUpload(['user_id' => 'document-owner-id']);

        $response = $this
            ->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('documents.delete'), [
                'id' => (string) $document->_id,
            ]);

        $response
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success', 'Document deleted successfully.');

        $this->assertNotNull($document->fresh()->deleted_at);
    }

    public function test_users_cannot_delete_documents_owned_by_other_users(): void
    {
        $user = $this->makeMongoUser(['_id' => 'document-owner-id']);
        $document = $this->createUpload(['user_id' => 'another-user-id']);

        $response = $this
            ->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('documents.delete'), [
                'id' => (string) $document->_id,
            ]);

        $response
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'Document not found.');

        $this->assertNull($document->fresh()->deleted_at);
    }

    public function test_authenticated_users_can_restore_their_own_documents(): void
    {
        $user = $this->makeMongoUser(['_id' => 'document-owner-id']);
        $document = $this->createUpload([
            'user_id' => 'document-owner-id',
            'deleted_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('documents.restore'), [
                'id' => (string) $document->_id,
            ]);

        $response
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success', 'Document restored successfully.');

        $this->assertNull($document->fresh()->deleted_at);
    }

    public function test_users_cannot_restore_documents_owned_by_other_users(): void
    {
        $user = $this->makeMongoUser(['_id' => 'document-owner-id']);
        $document = $this->createUpload([
            'user_id' => 'another-user-id',
            'deleted_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('documents.restore'), [
                'id' => (string) $document->_id,
            ]);

        $response
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'Document not found.');

        $this->assertNotNull($document->fresh()->deleted_at);
    }

    public function test_authenticated_users_can_toggle_favorite_on_their_documents(): void
    {
        $user = $this->makeMongoUser(['_id' => 'document-owner-id']);
        $document = $this->createUpload(['user_id' => 'document-owner-id']);

        $this
            ->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('favorites-documents.mark'), [
                'id' => (string) $document->_id,
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success', 'Favorite updated successfully.');

        $this->assertTrue((bool) $document->fresh()->favorite);

        $this
            ->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('favorites-documents.mark'), [
                'id' => (string) $document->_id,
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success', 'Favorite updated successfully.');

        $this->assertFalse((bool) $document->fresh()->favorite);
    }

    public function test_deleted_documents_cannot_be_marked_as_favorite(): void
    {
        $user = $this->makeMongoUser(['_id' => 'document-owner-id']);
        $document = $this->createUpload([
            'user_id' => 'document-owner-id',
            'deleted_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('favorites-documents.mark'), [
                'id' => (string) $document->_id,
            ]);

        $response
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'Document not found.');

        $this->assertFalse((bool) $document->fresh()->favorite);
    }

    public function test_missing_document_actions_return_not_found_feedback(): void
    {
        $user = $this->makeMongoUser(['_id' => 'document-owner-id']);

        $this
            ->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('documents.delete'), ['id' => 'missing-document-id'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'Document not found.');

        $this
            ->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('documents.restore'), ['id' => 'missing-document-id'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'Document not found.');

        $this
            ->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('favorites-documents.mark'), ['id' => 'missing-document-id'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'Document not found.');
    }

    private function createUpload(array $attributes = []): Upload
    {
        $deletedAt = $attributes['deleted_at'] ?? null;
        unset($attributes['deleted_at']);

        $upload = Upload::create(array_merge([
            'user_id' => 'document-owner-id',
            'original_name' => 'document.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'checksum' => hash('sha256', 'document.pdf'),
            'r2_bucket' => 'test-bucket',
            'r2_key' => 'uploads/document.pdf',
            'status' => 'uploaded',
            'meta' => [],
        ], $attributes));

        if ($deletedAt !== null) {
            $upload->deleted_at = $deletedAt;
            $upload->save();
        }

        return $upload;
    }
}
