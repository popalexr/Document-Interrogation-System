<?php

namespace Tests\Feature\Interrogations;

use App\Models\AIInterrogation;
use App\Models\AIInterrogationChat;
use App\Models\Chat;
use App\Models\Interrogation;
use App\Models\Upload;
use App\Services\McpStreamClient;
use GuzzleHttp\Psr7\Response;
use Mockery;
use Tests\TestCase;

class InterrogationsChatsTest extends TestCase
{
    public function test_document_interrogation_requires_document_id_and_query(): void
    {
        $response = $this
            ->actingAs($this->makeMongoUser())
            ->from(route('dashboard'))
            ->post(route('documents.interrogate.store'), [
                'query' => '',
            ]);

        $response
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasErrors(['document_id', 'query']);
    }

    public function test_document_interrogation_rejects_queries_longer_than_5000_characters(): void
    {
        $document = $this->createUpload(['user_id' => 'interrogation-user-id']);

        $response = $this
            ->actingAs($this->makeMongoUser(['_id' => 'interrogation-user-id']))
            ->from(route('dashboard'))
            ->post(route('documents.interrogate.store'), [
                'document_id' => (string) $document->_id,
                'query' => str_repeat('a', 5001),
            ]);

        $response
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasErrors('query');
    }

    public function test_users_cannot_interrogate_documents_owned_by_other_users(): void
    {
        $document = $this->createUpload(['user_id' => 'another-user-id']);

        $this->mockMcpClientShouldNotBeCalled();

        $response = $this
            ->actingAs($this->makeMongoUser(['_id' => 'interrogation-user-id']))
            ->post(route('documents.interrogate.store'), [
                'document_id' => (string) $document->_id,
                'query' => 'Summarize this document.',
            ]);

        $response
            ->assertNotFound()
            ->assertJsonPath('message', 'Document not found.');

        $this->assertSame(0, Chat::query()->count());
        $this->assertSame(0, Interrogation::query()->count());
    }

    public function test_document_interrogation_creates_chat_and_stores_user_and_assistant_messages(): void
    {
        $document = $this->createUpload(['user_id' => 'interrogation-user-id']);

        $this->mockMcpClientWithAnswer(
            endpoint: '/query',
            answer: 'This document is about quarterly planning.',
        );

        $response = $this
            ->actingAs($this->makeMongoUser(['_id' => 'interrogation-user-id']))
            ->post(route('documents.interrogate.store'), [
                'document_id' => (string) $document->_id,
                'query' => 'What is this document about?',
            ]);

        $response->assertOk();
        $this->assertStringContainsString('"newChat":true', $response->streamedContent());

        $chat = Chat::query()->first();
        $this->assertNotNull($chat);
        $this->assertSame((string) $document->_id, $chat->document_id);
        $this->assertSame('interrogation-user-id', $chat->user_id);

        $messages = Interrogation::query()
            ->where('chat_id', (string) $chat->_id)
            ->orderBy('_id', 'asc')
            ->get();

        $this->assertCount(2, $messages);
        $this->assertSame('user', $messages[0]->role);
        $this->assertSame('What is this document about?', $messages[0]->content);
        $this->assertSame('assistant', $messages[1]->role);
        $this->assertSame('This document is about quarterly planning.', $messages[1]->content);
    }

    public function test_ai_interrogation_requires_documents_and_query(): void
    {
        $response = $this
            ->actingAs($this->makeMongoUser())
            ->from(route('interrogations.index'))
            ->post(route('interrogations.store'), [
                'query' => '',
            ]);

        $response
            ->assertRedirect(route('interrogations.index'))
            ->assertSessionHasErrors(['documents_ids', 'query']);
    }

    public function test_ai_interrogation_rejects_documents_not_owned_by_user(): void
    {
        $document = $this->createUpload(['user_id' => 'another-user-id']);

        $this->mockMcpClientShouldNotBeCalled();

        $response = $this
            ->actingAs($this->makeMongoUser(['_id' => 'interrogation-user-id']))
            ->post(route('interrogations.store'), [
                'documents_ids' => [(string) $document->_id],
                'query' => 'Compare the documents.',
            ]);

        $response->assertUnprocessable();

        $this->assertSame(0, AIInterrogationChat::query()->count());
        $this->assertSame(0, AIInterrogation::query()->count());
    }

    public function test_ai_interrogation_creates_chat_and_stores_user_and_assistant_messages(): void
    {
        $document = $this->createUpload([
            'user_id' => 'interrogation-user-id',
            'vector_file_id' => 'vector-file-1',
        ]);

        $this->mockMcpClientWithAnswer(
            endpoint: '/ai_interrogation',
            answer: 'The selected document focuses on delivery risks.',
            citations: [
                ['file_id' => 'vector-file-1', 'filename' => 'document.pdf'],
            ],
        );

        $response = $this
            ->actingAs($this->makeMongoUser(['_id' => 'interrogation-user-id']))
            ->post(route('interrogations.store'), [
                'documents_ids' => [(string) $document->_id],
                'query' => 'What are the main risks?',
            ]);

        $response->assertOk();
        $this->assertStringContainsString('"newChat":true', $response->streamedContent());

        $chat = AIInterrogationChat::query()->first();
        $this->assertNotNull($chat);
        $this->assertSame('interrogation-user-id', $chat->user_id);

        $messages = AIInterrogation::query()
            ->where('chat_id', (string) $chat->_id)
            ->orderBy('_id', 'asc')
            ->get();

        $this->assertCount(2, $messages);
        $this->assertSame('user', $messages[0]->role);
        $this->assertSame([(string) $document->_id], $messages[0]->documents_ids);
        $this->assertSame('What are the main risks?', $messages[0]->content);
        $this->assertSame('assistant', $messages[1]->role);
        $this->assertSame('The selected document focuses on delivery risks.', $messages[1]->content);
        $this->assertSame([
            [
                'document_id' => (string) $document->_id,
                'original_name' => 'document.pdf',
                'file_id' => 'vector-file-1',
            ],
        ], $messages[1]->citations);
    }

    public function test_ai_interrogation_chat_delete_removes_chat_and_messages_for_owner(): void
    {
        $user = $this->makeMongoUser(['_id' => 'interrogation-user-id']);
        $chat = AIInterrogationChat::create(['user_id' => 'interrogation-user-id']);

        AIInterrogation::create([
            'chat_id' => (string) $chat->_id,
            'documents_ids' => [],
            'role' => 'user',
            'content' => 'Question',
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('interrogations.index'))
            ->post(route('interrogations.delete'), [
                'chat_id' => (string) $chat->_id,
            ]);

        $response
            ->assertRedirect(route('interrogations.index'))
            ->assertSessionHas('success', 'Chat has been deleted.');

        $this->assertSame(0, AIInterrogationChat::query()->count());
        $this->assertSame(0, AIInterrogation::query()->count());
    }

    private function mockMcpClientShouldNotBeCalled(): void
    {
        $client = Mockery::mock(McpStreamClient::class);
        $client->shouldNotReceive('postStream');

        $this->instance(McpStreamClient::class, $client);
    }

    private function mockMcpClientWithAnswer(string $endpoint, string $answer, array $citations = []): void
    {
        $client = Mockery::mock(McpStreamClient::class);
        $client->shouldReceive('postStream')
            ->once()
            ->with(
                Mockery::on(fn (string $url) => str_ends_with($url, $endpoint)),
                Mockery::on(fn (array $payload) => !blank($payload['user_id'] ?? null)
                    && !blank($payload['question'] ?? null)
                    && isset($payload['extra']['history']))
            )
            ->andReturn(new Response(200, [], $this->sseDoneEvent($answer, $citations)));

        $this->instance(McpStreamClient::class, $client);
    }

    private function sseDoneEvent(string $answer, array $citations = []): string
    {
        return 'data: '.json_encode([
            'type' => 'done',
            'answer' => $answer,
            'citations' => $citations,
        ])."\n\n";
    }

    private function createUpload(array $attributes = []): Upload
    {
        $vectorFileId = $attributes['vector_file_id'] ?? null;
        unset($attributes['vector_file_id']);

        $upload = Upload::create(array_merge([
            'user_id' => 'interrogation-user-id',
            'original_name' => 'document.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'checksum' => hash('sha256', 'document.pdf'),
            'r2_bucket' => 'test-bucket',
            'r2_key' => 'uploads/document.pdf',
            'status' => 'uploaded',
            'meta' => [],
        ], $attributes));

        if ($vectorFileId !== null) {
            $upload->vector_file_id = $vectorFileId;
            $upload->save();
        }

        return $upload;
    }
}
