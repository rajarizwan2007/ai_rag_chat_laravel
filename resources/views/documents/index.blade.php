@extends('layouts.app', ['title' => 'Documents'])

@php
    use App\Enums\DocumentStatus;

    $badge = [
        DocumentStatus::Pending->value => 'bg-stone-100 text-stone-700',
        DocumentStatus::Processing->value => 'bg-amber-100 text-amber-800',
        DocumentStatus::Ready->value => 'bg-emerald-100 text-emerald-800',
        DocumentStatus::Failed->value => 'bg-red-100 text-red-800',
    ];

    $busy = $documents->contains(fn ($d) => in_array($d->status, [DocumentStatus::Pending, DocumentStatus::Processing]));
@endphp

@if ($busy)
    @push('head')
        {{-- Refresh while documents are still being embedded in the queue. --}}
        <meta http-equiv="refresh" content="3">
    @endpush
@endif

@section('content')
    <section class="rounded-lg border border-stone-200 bg-white p-6">
        <h1 class="text-xl font-semibold">Upload a document</h1>
        <p class="mt-1 text-sm text-stone-500">PDF, TXT or Markdown, up to 10 MB. It will be split into chunks and embedded with Gemini.</p>

        <form action="{{ route('documents.store') }}" method="POST" enctype="multipart/form-data" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center">
            @csrf
            <input type="file" name="file" accept=".pdf,.txt,.md" required
                   class="block w-full text-sm file:mr-4 file:rounded-md file:border-0 file:bg-stone-100 file:px-4 file:py-2 file:text-sm file:font-medium hover:file:bg-stone-200">
            <button type="submit" class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700">
                Upload
            </button>
        </form>

        @error('file')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </section>

    <section class="mt-8">
        <h2 class="text-lg font-semibold">Your documents</h2>

        @if ($documents->isEmpty())
            <p class="mt-3 text-sm text-stone-500">No documents yet. Upload one above to get started.</p>
        @else
            <ul class="mt-3 divide-y divide-stone-200 rounded-lg border border-stone-200 bg-white">
                @foreach ($documents as $document)
                    <li class="flex items-center justify-between gap-4 px-4 py-3">
                        <div class="min-w-0">
                            <p class="truncate font-medium">{{ $document->title }}</p>
                            <p class="text-xs text-stone-500">
                                {{ $document->chunks_count }} {{ Str::plural('chunk', $document->chunks_count) }} · uploaded {{ $document->created_at->diffForHumans() }}
                            </p>
                            @if ($document->error)
                                <p class="mt-1 text-xs text-red-600">{{ $document->error }}</p>
                            @endif
                        </div>

                        <div class="flex shrink-0 items-center gap-3">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badge[$document->status->value] }}">
                                {{ ucfirst($document->status->value) }}
                            </span>
                            <form action="{{ route('documents.destroy', $document) }}" method="POST" onsubmit="return confirm('Delete this document?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm text-stone-500 hover:text-red-600">Delete</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
