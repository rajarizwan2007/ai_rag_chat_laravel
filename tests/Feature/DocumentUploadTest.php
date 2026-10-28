<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Jobs\ProcessDocument;
use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        Queue::fake();
    }

    public function test_uploading_a_document_stores_it_and_queues_processing(): void
    {
        $file = UploadedFile::fake()->createWithContent('policy.txt', 'Refunds are issued within 14 days.');

        $this->post(route('documents.store'), ['file' => $file])
            ->assertRedirect(route('documents.index'));

        $document = Document::sole();

        $this->assertSame('policy.txt', $document->title);
        $this->assertSame(DocumentStatus::Pending, $document->status);
        Storage::assertExists($document->path);
        Queue::assertPushed(ProcessDocument::class, fn ($job) => $job->document->is($document));
    }

    public function test_unsupported_file_types_are_rejected(): void
    {
        $file = UploadedFile::fake()->image('photo.png');

        $this->post(route('documents.store'), ['file' => $file])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('documents', 0);
        Queue::assertNothingPushed();
    }

    public function test_deleting_a_document_removes_its_file(): void
    {
        $path = UploadedFile::fake()->createWithContent('notes.md', '# Notes')->store('documents');
        $document = Document::create(['title' => 'notes.md', 'path' => $path, 'mime_type' => 'text/markdown', 'status' => DocumentStatus::Ready]);

        $this->delete(route('documents.destroy', $document))
            ->assertRedirect(route('documents.index'));

        $this->assertModelMissing($document);
        Storage::assertMissing($path);
    }

    public function test_documents_page_lists_uploads(): void
    {
        Document::create(['title' => 'handbook.pdf', 'path' => 'documents/x.pdf', 'mime_type' => 'application/pdf', 'status' => DocumentStatus::Ready]);

        $this->get(route('documents.index'))
            ->assertOk()
            ->assertSee('handbook.pdf')
            ->assertSee('Ready');
    }
}
