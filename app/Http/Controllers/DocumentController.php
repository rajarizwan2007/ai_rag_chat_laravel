<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Jobs\ProcessDocument;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function index(): View
    {
        return view('documents.index', [
            'documents' => Document::withCount('chunks')->latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,txt,md', 'max:10240'],
        ]);

        $file = $request->file('file');

        $document = Document::create([
            'title' => $file->getClientOriginalName(),
            'path' => $file->store('documents'),
            'mime_type' => $file->getMimeType(),
            'status' => DocumentStatus::Pending,
        ]);

        ProcessDocument::dispatch($document);

        return to_route('documents.index')->with('status', "Uploaded \"{$document->title}\". Processing in the background…");
    }

    public function destroy(Document $document): RedirectResponse
    {
        Storage::delete($document->path);
        $document->delete();

        return to_route('documents.index')->with('status', "Deleted \"{$document->title}\".");
    }
}
