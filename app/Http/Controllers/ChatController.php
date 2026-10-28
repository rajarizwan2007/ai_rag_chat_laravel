<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Models\Chunk;
use App\Models\Document;
use App\Services\RagService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Laravel\Ai\Exceptions\AiException;
use Laravel\Ai\Streaming\Events\TextDelta;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ChatController extends Controller
{
    protected const BUSY_MESSAGE = 'The AI provider is busy or unreachable right now. Please try again in a moment.';

    public function show(): View
    {
        return view('chat.show', [
            'documents' => $this->readyDocuments(),
            'result' => null,
        ]);
    }

    /**
     * Non-JavaScript fallback: answer in a normal page render.
     */
    public function ask(Request $request, RagService $rag): View|RedirectResponse
    {
        $validated = $this->validateQuestion($request);

        try {
            $result = $rag->ask($validated['question'], $validated['document_id'] ?? null);
        } catch (AiException $e) {
            report($e);

            return back()->withInput()->withErrors(['question' => self::BUSY_MESSAGE]);
        }

        return view('chat.show', [
            'documents' => $this->readyDocuments(),
            'result' => $result,
        ]);
    }

    /**
     * Stream the answer as Server-Sent Events: one "sources" event, then
     * "delta" events as tokens arrive, then "done" (or "error").
     */
    public function stream(Request $request, RagService $rag): StreamedResponse
    {
        $validated = $this->validateQuestion($request);

        return response()->stream(function () use ($rag, $validated) {
            try {
                [$sources, $stream] = $rag->stream($validated['question'], $validated['document_id'] ?? null);

                yield $this->sse('sources', $sources->map(fn (Chunk $chunk, int $i) => [
                    'number' => $i + 1,
                    'title' => $chunk->document->title,
                    'chunk' => $chunk->chunk_index + 1,
                    'similarity' => $chunk->similarity,
                    'excerpt' => Str::limit($chunk->content, 300),
                ])->values());

                if ($stream === null) {
                    yield $this->sse('delta', ['text' => RagService::NOT_FOUND]);
                } else {
                    foreach ($stream as $event) {
                        if ($event instanceof TextDelta) {
                            yield $this->sse('delta', ['text' => $event->delta]);
                        }
                    }
                }

                yield $this->sse('done', []);
            } catch (Throwable $e) {
                report($e);

                yield $this->sse('error', ['message' => self::BUSY_MESSAGE]);
            }
        }, headers: [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * @return array{question: string, document_id?: int|null}
     */
    protected function validateQuestion(Request $request): array
    {
        return $request->validate([
            'question' => ['required', 'string', 'max:1000'],
            'document_id' => ['nullable', 'integer', Rule::exists('documents', 'id')],
        ]);
    }

    protected function sse(string $event, mixed $data): string
    {
        return "event: {$event}\ndata: ".json_encode($data)."\n\n";
    }

    /**
     * @return Collection<int, Document>
     */
    protected function readyDocuments(): Collection
    {
        return Document::where('status', DocumentStatus::Ready)->orderBy('title')->get(['id', 'title']);
    }
}
