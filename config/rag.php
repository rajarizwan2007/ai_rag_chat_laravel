<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Chunking
    |--------------------------------------------------------------------------
    |
    | Documents are split into chunks of roughly this many characters before
    | embedding. Overlap carries the end of one chunk into the next so that
    | an idea split across a boundary is still retrievable.
    |
    */

    'chunk_size' => (int) env('RAG_CHUNK_SIZE', 800),
    'chunk_overlap' => (int) env('RAG_CHUNK_OVERLAP', 100),

    /*
    |--------------------------------------------------------------------------
    | Retrieval
    |--------------------------------------------------------------------------
    |
    | top_k: the most chunks handed to the model as context.
    |
    | min_similarity: cosine similarity floor (0–1). With Gemini embeddings,
    | unrelated text still scores ~0.5–0.6, so the floor sits just above that.
    |
    | relative_margin: also drop chunks scoring more than this below the best
    | match. A fixed floor alone can't separate "the answer" from "the same
    | topic, but noise", since scores shift from question to question.
    |
    */

    'top_k' => (int) env('RAG_TOP_K', 5),
    'min_similarity' => (float) env('RAG_MIN_SIMILARITY', 0.55),
    'relative_margin' => (float) env('RAG_RELATIVE_MARGIN', 0.06),

];
