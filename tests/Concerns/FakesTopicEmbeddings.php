<?php

namespace Tests\Concerns;

use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;

/**
 * Replaces the embeddings API with deterministic vectors: each topic keyword
 * gets its own dimension, so texts sharing a topic are similar (cosine ≈ 1)
 * and texts on different topics are not (cosine ≈ 0).
 */
trait FakesTopicEmbeddings
{
    protected array $topics = ['refund', 'shipping', 'warranty'];

    protected function fakeTopicEmbeddings(): void
    {
        Embeddings::fake(fn (EmbeddingsPrompt $prompt) => array_map(
            fn (string $text) => $this->topicVector($text, $prompt->dimensions),
            $prompt->inputs,
        ))->preventStrayEmbeddings();
    }

    /**
     * @return list<float>
     */
    protected function topicVector(string $text, int $dimensions = 768): array
    {
        $vector = array_fill(0, $dimensions, 0.0);

        foreach ($this->topics as $i => $topic) {
            if (str_contains(strtolower($text), $topic)) {
                $vector[$i] = 1.0;
            }
        }

        // A small shared baseline so no vector is all zeros.
        $vector[$dimensions - 1] = 0.1;

        return $vector;
    }
}
