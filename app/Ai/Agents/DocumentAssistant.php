<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * The "generation" step of RAG. It receives the user's question together with
 * the retrieved chunks and must answer from those chunks only.
 */
class DocumentAssistant implements Agent
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
        You answer questions about the user's documents.

        Rules:
        - Use ONLY the numbered context passages you are given. Do not use outside knowledge.
        - Cite the passages you used inline, like [1] or [2][3].
        - If the context does not contain the answer, say "I couldn't find that in your documents." and nothing else.
        - Be concise: a few sentences or a short list.
        TEXT;
    }
}
