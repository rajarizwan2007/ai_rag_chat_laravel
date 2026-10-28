# DocChat — RAG with Laravel 13

Ask questions about your own documents. DocChat is a retrieval-augmented generation (RAG) app built with Laravel 13, PostgreSQL + pgvector and the Laravel AI SDK (Gemini).

Upload a PDF, TXT or Markdown file, then ask a question: the answer is grounded in your documents, cites its sources, and streams in as it is generated.

## How it works

```
INGEST   Upload → extract text → split into overlapping chunks (~800 chars)
         → queued job → embed chunks with Gemini (768 dims) → store in pgvector (HNSW index)

QUERY    Question → embed → nearest chunks by cosine similarity
         → keep chunks above a floor and close to the best match
         → prompt the model with numbered passages → stream a cited answer
```

| Piece | Where |
|---|---|
| Text extraction | `app/Services/TextExtractor.php` |
| Chunking | `app/Services/TextChunker.php` |
| Embedding job | `app/Jobs/ProcessDocument.php` |
| Retrieval + prompt | `app/Services/RagService.php` |
| Answering agent | `app/Ai/Agents/DocumentAssistant.php` |
| Streaming (SSE) | `app/Http/Controllers/ChatController.php`, `resources/js/chat.js` |
| Tuning | `config/rag.php` |

## Stack

Laravel 13 · PHP 8.5 · PostgreSQL 18 + pgvector · Laravel AI SDK · Gemini · Laravel Sail (Docker) · Tailwind CSS

## Running locally

Requires Docker (on Windows, run from WSL2).

```bash
git clone https://github.com/rajarizwan2007/ai_rag_chat_laravel.git docchat
cd docchat
cp .env.example .env            # then set GEMINI_API_KEY
docker run --rm -v "$(pwd)":/opt -w /opt laravelsail/php84-composer:latest composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install && ./vendor/bin/sail npm run build
```

Open http://localhost. A `queue` container processes uploads in the background.

Get a free Gemini API key at https://aistudio.google.com/apikey.

## Tests

```bash
./vendor/bin/sail artisan test
```
