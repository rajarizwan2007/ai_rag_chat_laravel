<li class="rounded-lg border border-stone-200 bg-white p-4 text-sm">
    <p class="flex items-center justify-between gap-3">
        <span class="font-medium">
            [<span data-field="number">{{ $number }}</span>] <span data-field="title">{{ $title }}</span>
            <span class="font-normal text-stone-500">· chunk <span data-field="chunk">{{ $chunk }}</span></span>
        </span>
        <span class="shrink-0 rounded-full bg-stone-100 px-2 py-0.5 text-xs text-stone-600" title="Cosine similarity to your question">
            <span data-field="similarity">{{ $similarity !== null ? round($similarity * 100).'%' : '' }}</span> match
        </span>
    </p>
    <p data-field="excerpt" class="mt-1 text-stone-600">{{ $excerpt }}</p>
</li>
