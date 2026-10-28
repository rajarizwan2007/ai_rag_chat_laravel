<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Jobs\ProcessDocument;
use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;
use RuntimeException;
use Tests\Concerns\FakesTopicEmbeddings;
use Tests\TestCase;

class ProcessDocumentTest extends TestCase
{
    use FakesTopicEmbeddings, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        $this->fakeTopicEmbeddings();
    }

    public function test_it_chunks_embeds_and_stores_a_document(): void
    {
        $document = $this->documentWithText(
            "Refunds are issued within 14 days of purchase.\n\nShipping takes 3 to 5 business days."
        );

        ProcessDocument::dispatchSync($document);

        $document->refresh();
        $this->assertSame(DocumentStatus::Ready, $document->status);
        $this->assertCount(1, $document->chunks);
        $this->assertCount(768, $document->chunks->first()->embedding);

        Embeddings::assertGenerated(fn (EmbeddingsPrompt $prompt) => $prompt->contains('Refunds are issued'));
    }

    public function test_long_documents_are_split_into_ordered_chunks(): void
    {
        $paragraphs = collect(range(1, 30))->map(fn ($i) => "Paragraph {$i}: ".str_repeat('warranty details ', 8));

        $document = $this->documentWithText($paragraphs->implode("\n\n"));

        ProcessDocument::dispatchSync($document);

        $chunks = $document->chunks()->get();
        $this->assertGreaterThan(1, $chunks->count());
        $this->assertSame(range(0, $chunks->count() - 1), $chunks->pluck('chunk_index')->all());
    }

    public function test_reprocessing_replaces_old_chunks(): void
    {
        $document = $this->documentWithText('Refunds are issued within 14 days.');

        ProcessDocument::dispatchSync($document);
        ProcessDocument::dispatchSync($document);

        $this->assertSame(1, $document->chunks()->count());
    }

    public function test_a_document_without_text_fails(): void
    {
        $document = $this->documentWithText("  \n\n  ");
        $job = new ProcessDocument($document);

        try {
            app()->call([$job, 'handle']);
            $this->fail('Expected the job to throw.');
        } catch (RuntimeException $e) {
            $job->failed($e);
        }

        $document->refresh();
        $this->assertSame(DocumentStatus::Failed, $document->status);
        $this->assertSame('No readable text found in this document.', $document->error);
        Embeddings::assertNothingGenerated();
    }

    protected function documentWithText(string $text): Document
    {
        Storage::put('documents/test.txt', $text);

        return Document::create([
            'title' => 'test.txt',
            'path' => 'documents/test.txt',
            'mime_type' => 'text/plain',
            'status' => DocumentStatus::Pending,
        ]);
    }
}
