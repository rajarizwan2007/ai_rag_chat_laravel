<?php

namespace App\Providers;

use App\Ai\Agents\DocumentAssistant;
use App\Services\RagService;
use App\Services\TextChunker;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TextChunker::class, fn () => new TextChunker(
            chunkSize: config('rag.chunk_size'),
            overlap: config('rag.chunk_overlap'),
        ));

        $this->app->bind(RagService::class, fn ($app) => new RagService(
            assistant: $app->make(DocumentAssistant::class),
            topK: config('rag.top_k'),
            minSimilarity: config('rag.min_similarity'),
            relativeMargin: config('rag.relative_margin'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
