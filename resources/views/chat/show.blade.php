@extends('layouts.app', ['title' => 'Chat'])

@section('content')
    @if ($documents->isEmpty())
        <div class="rounded-lg border border-stone-200 bg-white p-6 text-sm text-stone-600">
            No documents are ready yet. <a href="{{ route('documents.index') }}" class="font-medium text-stone-900 underline">Upload one</a> first.
        </div>
    @else
        {{-- Works as a plain form post; resources/js/chat.js upgrades it to a streamed answer. --}}
        <form id="chat-form" action="{{ route('chat.ask') }}" data-stream-url="{{ route('chat.stream') }}" method="POST"
              class="rounded-lg border border-stone-200 bg-white p-6">
            @csrf
            <label for="question" class="text-xl font-semibold">Ask your documents</label>

            <textarea id="question" name="question" rows="3" required maxlength="1000"
                      placeholder="e.g. What is the refund policy?"
                      class="mt-3 block w-full rounded-md border border-stone-300 px-3 py-2 text-sm focus:border-stone-900 focus:outline-none">{{ old('question', $result?->question) }}</textarea>
            <p id="chat-error" class="mt-1 text-sm text-red-600 {{ $errors->has('question') ? '' : 'hidden' }}">{{ $errors->first('question') }}</p>

            <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <select name="document_id" class="rounded-md border border-stone-300 px-3 py-2 text-sm">
                    <option value="">Search all documents</option>
                    @foreach ($documents as $document)
                        <option value="{{ $document->id }}" @selected(old('document_id', request('document_id')) == $document->id)>
                            {{ $document->title }}
                        </option>
                    @endforeach
                </select>

                <button type="submit" class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700 disabled:opacity-50">
                    Ask
                </button>
            </div>
        </form>
    @endif

    <section id="chat-answer" class="mt-8 rounded-lg border border-stone-200 bg-white p-6 {{ $result ? '' : 'hidden' }}">
        <h2 class="text-sm font-medium uppercase tracking-wide text-stone-500">Answer</h2>
        <div id="chat-answer-text" class="mt-2 whitespace-pre-line leading-relaxed">{{ $result?->answer }}</div>
    </section>

    <section id="chat-sources" class="mt-6 {{ $result?->sources->isNotEmpty() ? '' : 'hidden' }}">
        <h2 class="text-sm font-medium uppercase tracking-wide text-stone-500">Sources</h2>
        <ol id="chat-sources-list" class="mt-2 space-y-3">
            @foreach ($result?->sources ?? [] as $i => $chunk)
                @include('chat.source', [
                    'number' => $i + 1,
                    'title' => $chunk->document->title,
                    'chunk' => $chunk->chunk_index + 1,
                    'similarity' => $chunk->similarity,
                    'excerpt' => Str::limit($chunk->content, 300),
                ])
            @endforeach
        </ol>
    </section>

    {{-- Cloned by chat.js for streamed sources. --}}
    <template id="chat-source-template">
        @include('chat.source', ['number' => '', 'title' => '', 'chunk' => '', 'similarity' => null, 'excerpt' => ''])
    </template>
@endsection
