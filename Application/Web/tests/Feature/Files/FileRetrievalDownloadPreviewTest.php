<?php

namespace Tests\Feature\Files;

use App\Models\Edit;
use App\Models\Upload;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileRetrievalDownloadPreviewTest extends TestCase
{
    public function test_file_retrieval_requires_a_valid_query(): void
    {
        $response = $this
            ->actingAs($this->makeMongoUser())
            ->from(route('dashboard'))
            ->post(route('documents.retrieve'), [
                'query' => 'a',
            ]);

        $response
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasErrors('query');
    }

    public function test_file_retrieval_resolves_only_owned_non_deleted_documents(): void
    {
        Http::fake([
            '*' => Http::response([
                'search_query' => 'budget',
                'results' => [
                    [
                        'document_id' => null,
                        'file_id' => 'vector-owned',
                        'score' => 0.97,
                        'content' => [
                            ['text' => 'Budget plan for Q2.'],
                            ['text' => 'Includes delivery assumptions.'],
                        ],
                    ],
                    [
                        'document_id' => null,
                        'file_id' => 'vector-other-user',
                        'score' => 0.91,
                        'content' => [['text' => 'Should not be returned.']],
                    ],
                    [
                        'document_id' => null,
                        'file_id' => 'vector-deleted',
                        'score' => 0.82,
                        'content' => [['text' => 'Deleted document.']],
                    ],
                ],
            ]),
        ]);

        $owned = $this->createUpload([
            'user_id' => 'file-user-id',
            'original_name' => 'budget.pdf',
            'vector_file_id' => 'vector-owned',
        ]);
        $this->createUpload([
            'user_id' => 'another-user-id',
            'vector_file_id' => 'vector-other-user',
        ]);
        $this->createUpload([
            'user_id' => 'file-user-id',
            'vector_file_id' => 'vector-deleted',
            'deleted_at' => now(),
        ]);

        $response = $this
            ->actingAs($this->makeMongoUser(['_id' => 'file-user-id']))
            ->postJson(route('documents.retrieve'), [
                'query' => 'budget',
                'limit' => 3,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('query', 'budget')
            ->assertJsonCount(1, 'files')
            ->assertJsonPath('files.0._id', (string) $owned->_id)
            ->assertJsonPath('files.0.original_name', 'budget.pdf')
            ->assertJsonPath('files.0.score', 0.97)
            ->assertJsonPath('files.0.snippet', 'Budget plan for Q2. Includes delivery assumptions.');

        Http::assertSent(fn ($request) => $request['user_id'] === 'file-user-id'
            && $request['query'] === 'budget'
            && $request['max_num_results'] === 3);
    }

    public function test_users_can_download_their_own_uploaded_file(): void
    {
        Storage::fake('r2');
        Storage::disk('r2')->put('uploads/report.txt', 'report contents');

        $document = $this->createUpload([
            'user_id' => 'file-user-id',
            'original_name' => 'report.txt',
            'mime_type' => 'text/plain',
            'r2_key' => 'uploads/report.txt',
        ]);

        $response = $this
            ->actingAs($this->makeMongoUser(['_id' => 'file-user-id']))
            ->from(route('dashboard'))
            ->get(route('documents.downloadDocument', [
                'id' => (string) $document->_id,
            ]));

        $response->assertOk();
        $this->assertStringStartsWith('text/plain', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('report.txt', $response->headers->get('Content-Disposition'));
        $this->assertSame('report contents', $response->streamedContent());
    }

    public function test_users_cannot_download_files_owned_by_other_users(): void
    {
        Storage::fake('r2');
        Storage::disk('r2')->put('uploads/report.txt', 'report contents');

        $document = $this->createUpload([
            'user_id' => 'another-user-id',
            'r2_key' => 'uploads/report.txt',
        ]);

        $response = $this
            ->actingAs($this->makeMongoUser(['_id' => 'file-user-id']))
            ->from(route('dashboard'))
            ->get(route('documents.downloadDocument', [
                'id' => (string) $document->_id,
            ]));

        $response
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'File not found.');
    }

    public function test_view_file_api_streams_only_owned_non_deleted_uploads(): void
    {
        Storage::fake('r2');
        Storage::disk('r2')->put('uploads/preview.txt', 'preview contents');

        $document = $this->createUpload([
            'user_id' => 'file-user-id',
            'original_name' => 'preview.txt',
            'mime_type' => 'text/plain',
            'r2_key' => 'uploads/preview.txt',
        ]);

        $response = $this
            ->actingAs($this->makeMongoUser(['_id' => 'file-user-id']))
            ->get(route('api.viewFile').'?id='.(string) $document->_id);

        $response->assertOk();
        $this->assertStringStartsWith('text/plain', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('inline;', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('preview.txt', $response->headers->get('Content-Disposition'));
        $this->assertSame('preview contents', $response->streamedContent());

        $deleted = $this->createUpload([
            'user_id' => 'file-user-id',
            'r2_key' => 'uploads/preview.txt',
            'deleted_at' => now(),
        ]);

        $this->assertNotSame((string) $document->_id, (string) $deleted->_id);
        $this->assertNotNull($deleted->fresh()->deleted_at);
        $this->assertNotNull(Upload::query()->whereKey((string) $deleted->_id)->first()->deleted_at);

        $this
            ->actingAs($this->makeMongoUser(['_id' => 'file-user-id']))
            ->get(route('api.viewFile').'?id='.(string) $deleted->_id)
            ->assertNotFound();
    }

    public function test_view_file_api_rejects_files_owned_by_other_users(): void
    {
        Storage::fake('r2');
        Storage::disk('r2')->put('uploads/preview.txt', 'preview contents');

        $document = $this->createUpload([
            'user_id' => 'another-user-id',
            'r2_key' => 'uploads/preview.txt',
        ]);

        $this
            ->actingAs($this->makeMongoUser(['_id' => 'file-user-id']))
            ->get(route('api.viewFile').'?id='.(string) $document->_id)
            ->assertNotFound();
    }

    public function test_view_edited_file_api_streams_only_owned_edits(): void
    {
        Storage::fake('r2');
        Storage::disk('r2')->put('edits/preview.txt', 'edited contents');

        $edit = $this->createEdit([
            'user_id' => 'file-user-id',
            'original_name' => 'edited.txt',
            'r2_key' => 'edits/preview.txt',
        ]);

        $response = $this
            ->actingAs($this->makeMongoUser(['_id' => 'file-user-id']))
            ->get(route('api.viewEditedFile').'?id='.(string) $edit->_id);

        $response->assertOk();
        $this->assertStringContainsString('inline;', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('edited.txt', $response->headers->get('Content-Disposition'));
        $this->assertSame('edited contents', $response->streamedContent());

        $otherEdit = $this->createEdit([
            'user_id' => 'another-user-id',
            'r2_key' => 'edits/preview.txt',
        ]);

        $this->assertNotSame((string) $edit->_id, (string) $otherEdit->_id);
        $this->assertSame('another-user-id', $otherEdit->fresh()->user_id);

        $this
            ->actingAs($this->makeMongoUser(['_id' => 'file-user-id']))
            ->get(route('api.viewEditedFile').'?id='.(string) $otherEdit->_id)
            ->assertNotFound();
    }

    public function test_collabora_preview_redirects_for_owned_supported_files(): void
    {
        config()->set('services.collabora.public_url', 'https://collabora.example.test');
        config()->set('services.collabora.wopi_url', 'https://app.example.test');

        $document = $this->createUpload([
            'user_id' => 'file-user-id',
            'original_name' => 'deck.pptx',
        ]);

        $response = $this
            ->actingAs($this->makeMongoUser(['_id' => 'file-user-id']))
            ->get(route('collabora.preview', [
                'id' => (string) $document->_id,
            ]));

        $response->assertRedirectContains('https://collabora.example.test/browser/dist/cool.html?');
        $response->assertRedirectContains('WOPISrc=');
        $response->assertRedirectContains('access_token=');
    }

    public function test_collabora_preview_rejects_other_users_and_unsupported_files(): void
    {
        $otherUserDocument = $this->createUpload([
            'user_id' => 'another-user-id',
            'original_name' => 'deck.pptx',
        ]);
        $unsupportedDocument = $this->createUpload([
            'user_id' => 'file-user-id',
            'original_name' => 'notes.txt',
        ]);

        $this
            ->actingAs($this->makeMongoUser(['_id' => 'file-user-id']))
            ->get(route('collabora.preview', ['id' => (string) $otherUserDocument->_id]))
            ->assertNotFound();

        $this
            ->actingAs($this->makeMongoUser(['_id' => 'file-user-id']))
            ->get(route('collabora.preview', ['id' => (string) $unsupportedDocument->_id]))
            ->assertStatus(415);
    }

    private function createUpload(array $attributes = []): Upload
    {
        $deletedAt = $attributes['deleted_at'] ?? null;
        $vectorFileId = $attributes['vector_file_id'] ?? null;
        unset($attributes['deleted_at'], $attributes['vector_file_id']);

        $upload = Upload::create(array_merge([
            'user_id' => 'file-user-id',
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
        }

        if ($vectorFileId !== null) {
            $upload->vector_file_id = $vectorFileId;
        }

        if ($deletedAt !== null || $vectorFileId !== null) {
            $upload->save();
        }

        return $upload;
    }

    private function createEdit(array $attributes = []): Edit
    {
        return Edit::create(array_merge([
            'original_document_id' => 'original-document-id',
            'user_id' => 'file-user-id',
            'original_name' => 'edited.txt',
            'r2_key' => 'edits/edited.txt',
            'created_at' => now(),
        ], $attributes));
    }
}
