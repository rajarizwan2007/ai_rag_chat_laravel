<?php

namespace Tests\Unit;

use App\Services\TextChunker;
use PHPUnit\Framework\TestCase;

class TextChunkerTest extends TestCase
{
    public function test_short_text_becomes_a_single_chunk(): void
    {
        $chunks = (new TextChunker)->chunk("First paragraph.\n\nSecond paragraph.");

        $this->assertSame(['First paragraph. Second paragraph.'], $chunks);
    }

    public function test_no_chunk_exceeds_the_size_limit(): void
    {
        $text = str_repeat('This is a sentence about Laravel. ', 200);

        $chunks = (new TextChunker(chunkSize: 300, overlap: 50))->chunk($text);

        $this->assertGreaterThan(1, count($chunks));

        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(300, mb_strlen($chunk));
        }
    }

    public function test_consecutive_chunks_overlap(): void
    {
        $paragraphs = array_map(fn ($i) => "Paragraph number {$i} has some words in it.", range(1, 40));

        $chunks = (new TextChunker(chunkSize: 200, overlap: 40))->chunk(implode("\n\n", $paragraphs));

        $lastWord = collect(explode(' ', $chunks[0]))->last();

        $this->assertStringContainsString($lastWord, $chunks[1]);
    }

    public function test_empty_text_has_no_chunks(): void
    {
        $this->assertSame([], (new TextChunker)->chunk("  \n\n  "));
    }
}
