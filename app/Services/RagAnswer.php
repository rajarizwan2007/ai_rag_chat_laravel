<?php

namespace App\Services;

use App\Models\Chunk;
use Illuminate\Support\Collection;

class RagAnswer
{
    /**
     * @param  Collection<int, Chunk>  $sources  The retrieved chunks, in citation order ([1], [2], …).
     */
    public function __construct(
        public string $question,
        public string $answer,
        public Collection $sources,
    ) {}
}
