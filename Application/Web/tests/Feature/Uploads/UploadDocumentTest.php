<?php

namespace Tests\Feature\Uploads;

use App\Events\Uploads\FileUpload;
use App\Models\Upload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadDocumentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('clamav.skip_validation', true);
    }

    public function test_guests_cannot_upload_documents(): void
    {
        $response = $this->post(route('uploads.store'), [
            'file' => UploadedFile::fake()->create('document.pdf', 12, 'application/pdf'),
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_file_is_required_to_upload_a_document(): void
    {
        $response = $this
            ->actingAs($this->makeMongoUser())
            ->from(route('dashboard'))
            ->post(route('uploads.store'));

        $response
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasErrors('file');
    }

    public function test_unsupported_file_types_are_rejected(): void
    {
        $response = $this
            ->actingAs($this->makeMongoUser())
            ->from(route('dashboard'))
            ->post(route('uploads.store'), [
                'file' => UploadedFile::fake()->create('payload.exe', 12, 'application/x-msdownload'),
            ]);

        $response
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasErrors('file');
    }

    public function test_authenticated_users_can_upload_documents(): void
    {
        Storage::fake('r2');
        Event::fake([FileUpload::class]);

        $user = $this->makeMongoUser(['_id' => 'upload-user-id']);
        $file = UploadedFile::fake()->create('Project Notes.md', 8, 'text/markdown');

        $response = $this
            ->actingAs($user)
            ->post(route('uploads.store'), [
                'file' => $file,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('status', 'uploading')
            ->assertJsonPath('mime_type', 'text/markdown')
            ->assertJsonPath('original_name', 'Project Notes.md');

        $upload = Upload::query()->where('user_id', 'upload-user-id')->first();

        $this->assertNotNull($upload);
        $this->assertSame('Project Notes.md', $upload->original_name);
        $this->assertSame('text/markdown', $upload->mime_type);
        $this->assertSame('uploading', $upload->status);
        $this->assertStringStartsWith('uploads/', $upload->r2_key);
        $this->assertStringEndsWith('/project-notes.md', $upload->r2_key);

        Storage::disk('r2')->assertExists($upload->r2_key);

        Event::assertDispatched(FileUpload::class, function (FileUpload $event) use ($upload) {
            return $event->fileId === (string) $upload->_id;
        });
    }
}
