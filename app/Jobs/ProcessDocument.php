<?php

namespace App\Jobs;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Services\TextChunker;
use App\Services\TextExtractor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Embeddings;
use RuntimeException;
use Throwable;

/**
 * The ingestion half of RAG: extract text, chunk it, embed each chunk and
 * store the vectors so they can be searched by meaning later.
 */
class ProcessDocument implements ShouldQueue
{
    use Queueable;

    /**
     * Embedding API calls can hit rate limits, so retry with a back-off.
     */
    public int $tries = 3;

    public array $backoff = [10, 30];

    /**
     * How many chunks to send to the embeddings API per request.
     */
    protected const BATCH_SIZE = 50;

    public function __construct(public Document $document) {}

    public function handle(TextExtractor $extractor, TextChunker $chunker): void
    {
        $this->document->update(['status' => DocumentStatus::Processing, 'error' => null]);

        $text = $extractor->extract(
            Storage::path($this->document->path),
            $this->document->mime_type,
        );

        $chunks = $chunker->chunk($text);

        if ($chunks === []) {
            throw new RuntimeException('No readable text found in this document.');
        }

        $rows = [];

        foreach (array_chunk($chunks, self::BATCH_SIZE) as $batch) {
            $embeddings = Embeddings::for($batch)->generate()->embeddings;

            foreach ($batch as $i => $content) {
                $rows[] = [
                    'chunk_index' => count($rows),
                    'content' => $content,
                    'embedding' => $embeddings[$i],
                ];
            }
        }

        // Replace chunks atomically so a retry never leaves duplicates behind.
        DB::transaction(function () use ($rows) {
            $this->document->chunks()->delete();
            $this->document->chunks()->createMany($rows);
            $this->document->update(['status' => DocumentStatus::Ready]);
        });
    }

    public function failed(?Throwable $exception): void
    {
        $this->document->update([
            'status' => DocumentStatus::Failed,
            'error' => $exception?->getMessage(),
        ]);
    }
}
