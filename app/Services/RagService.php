<?php

namespace App\Services;

use App\Ai\Agents\DocumentAssistant;
use App\Enums\DocumentStatus;
use App\Models\Chunk;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\StreamableAgentResponse;

/**
 * The query half of RAG: Retrieve the most relevant chunks, Augment the
 * prompt with them, and let the model Generate a grounded answer.
 */
class RagService
{
    public const NOT_FOUND = "I couldn't find that in your documents.";

    /**
     * @param  int  $topK  How many chunks to give the model as context.
     * @param  float  $minSimilarity  Cosine similarity (0–1) a chunk needs to count as relevant.
     * @param  float  $relativeMargin  Drop chunks scoring more than this below the best match.
     */
    public function __construct(
        protected DocumentAssistant $assistant,
        protected int $topK = 5,
        protected float $minSimilarity = 0.55,
        protected float $relativeMargin = 0.06,
    ) {}

    public function ask(string $question, ?int $documentId = null): RagAnswer
    {
        $sources = $this->retrieve($question, $documentId);

        // Without relevant context we skip the LLM call entirely: no
        // context means nothing to ground an answer in, and it saves tokens.
        if ($sources->isEmpty()) {
            return new RagAnswer($question, self::NOT_FOUND, $sources);
        }

        $answer = (string) $this->assistant->prompt($this->buildPrompt($question, $sources), timeout: 30);

        return new RagAnswer($question, trim($answer), $sources);
    }

    /**
     * Same as ask(), but the answer is streamed token by token. Returns null
     * for the stream when nothing relevant was found.
     *
     * @return array{0: Collection<int, Chunk>, 1: StreamableAgentResponse|null}
     */
    public function stream(string $question, ?int $documentId = null): array
    {
        $sources = $this->retrieve($question, $documentId);

        if ($sources->isEmpty()) {
            return [$sources, null];
        }

        return [$sources, $this->assistant->stream($this->buildPrompt($question, $sources), timeout: 30)];
    }

    /**
     * 1. Retrieve: embed the question and find the nearest chunks with pgvector.
     *
     * Each returned chunk carries a `similarity` attribute (0–1).
     *
     * @return Collection<int, Chunk>
     */
    public function retrieve(string $question, ?int $documentId = null): Collection
    {
        // Embed once and reuse the vector for filtering, ordering and scoring.
        $vector = Str::of($question)->toEmbeddings(cache: true);

        $chunks = Chunk::query()
            ->with('document')
            ->select('chunks.*')
            ->selectVectorDistance('embedding', $vector, 'distance')
            ->whereHas('document', fn ($query) => $query->where('status', DocumentStatus::Ready))
            ->when($documentId, fn ($query) => $query->where('document_id', $documentId))
            ->whereVectorSimilarTo('embedding', $vector, minSimilarity: $this->minSimilarity)
            ->limit($this->topK)
            ->get()
            ->each(fn (Chunk $chunk) => $chunk->similarity = round(1 - $chunk->distance, 3));

        if ($chunks->isEmpty()) {
            return $chunks;
        }

        // Keep only chunks close to the best match; the rest are usually
        // "same vocabulary, different topic" noise.
        $cutoff = $chunks->first()->similarity - $this->relativeMargin;

        return $chunks->filter(fn (Chunk $chunk) => $chunk->similarity >= $cutoff)->values();
    }

    /**
     * 2. Augment: put the retrieved chunks into the prompt as numbered passages.
     *
     * @param  Collection<int, Chunk>  $sources
     */
    public function buildPrompt(string $question, Collection $sources): string
    {
        $context = $sources
            ->values()
            ->map(fn (Chunk $chunk, int $i) => '['.($i + 1)."] (from \"{$chunk->document->title}\")\n{$chunk->content}")
            ->implode("\n\n");

        return "Context:\n\n{$context}\n\nQuestion: {$question}";
    }
}
