<?php

namespace App\Services;

class TextChunker
{
    /**
     * @param  int  $chunkSize  Target maximum characters per chunk.
     * @param  int  $overlap  Characters carried over from the previous chunk, so
     *                        a sentence cut at a boundary still has context.
     */
    public function __construct(
        protected int $chunkSize = 800,
        protected int $overlap = 100,
    ) {}

    /**
     * Split text into overlapping chunks, preferring paragraph and sentence boundaries.
     *
     * @return list<string>
     */
    public function chunk(string $text): array
    {
        $chunks = [];
        $current = '';

        foreach ($this->pieces($text) as $piece) {
            if ($current !== '' && mb_strlen($current) + mb_strlen($piece) + 1 > $this->chunkSize) {
                $chunks[] = trim($current);
                $current = $this->tail($current);
            }

            $current .= ($current === '' ? '' : ' ').$piece;
        }

        if (trim($current) !== '') {
            $chunks[] = trim($current);
        }

        return $chunks;
    }

    /**
     * Break text into paragraphs, then sentences, then hard slices, so that no
     * single piece is larger than a chunk.
     *
     * @return list<string>
     */
    protected function pieces(string $text): array
    {
        $pieces = [];

        foreach (preg_split("/\n\s*\n/", $text) as $paragraph) {
            $paragraph = trim(preg_replace('/\s+/', ' ', $paragraph));

            if ($paragraph === '') {
                continue;
            }

            if (mb_strlen($paragraph) <= $this->chunkSize) {
                $pieces[] = $paragraph;

                continue;
            }

            foreach (preg_split('/(?<=[.!?])\s+/', $paragraph) as $sentence) {
                foreach (mb_str_split($sentence, $this->chunkSize) as $slice) {
                    $pieces[] = $slice;
                }
            }
        }

        return $pieces;
    }

    /**
     * The last $overlap characters of a chunk, starting on a word boundary.
     */
    protected function tail(string $chunk): string
    {
        if ($this->overlap <= 0) {
            return '';
        }

        $tail = mb_substr($chunk, -$this->overlap);
        $space = mb_strpos($tail, ' ');

        return $space === false ? $tail : mb_substr($tail, $space + 1);
    }
}
