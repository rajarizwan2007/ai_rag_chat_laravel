<?php

namespace App\Services;

use InvalidArgumentException;
use Smalot\PdfParser\Parser;

class TextExtractor
{
    /**
     * Extract plain text from a file on disk.
     */
    public function extract(string $absolutePath, string $mimeType): string
    {
        $text = match (true) {
            $mimeType === 'application/pdf' => (new Parser)->parseFile($absolutePath)->getText(),
            str_starts_with($mimeType, 'text/') => file_get_contents($absolutePath),
            default => throw new InvalidArgumentException("Unsupported file type [{$mimeType}]."),
        };

        return $this->normalize($text);
    }

    /**
     * Tidy whitespace while keeping paragraph breaks, which the chunker relies on.
     */
    protected function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }
}
