<?php

namespace Tests\Feature;

use App\Ai\Agents\DocumentAssistant;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Services\RagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Prompts\AgentPrompt;
use Tests\Concerns\FakesTopicEmbeddings;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use FakesTopicEmbeddings, RefreshDatabase;

    protected Document $policy;

    protected Document $faq;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeTopicEmbeddings();

        $this->policy = $this->readyDocument('policy.pdf', [
            'Refunds are issued within 14 days of purchase.',
            'Shipping takes 3 to 5 business days.',
        ]);

        $this->faq = $this->readyDocument('faq.md', [
            'Every product has a two year warranty.',
        ]);
    }

    public function test_it_answers_using_only_the_relevant_chunks(): void
    {
        DocumentAssistant::fake(['Refunds are issued within 14 days [1].']);

        $this->post(route('chat.ask'), ['question' => 'How long do refunds take?'])
            ->assertOk()
            ->assertSee('Refunds are issued within 14 days [1].')
            ->assertSee('policy.pdf');

        DocumentAssistant::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains('[1] (from "policy.pdf")')
            && $prompt->contains('Refunds are issued within 14 days of purchase.')
            && ! $prompt->contains('Shipping takes')
            && ! $prompt->contains('warranty')
            && $prompt->contains('Question: How long do refunds take?'));
    }

    public function test_it_does_not_call_the_model_when_nothing_is_relevant(): void
    {
        DocumentAssistant::fake()->preventStrayPrompts();

        $this->post(route('chat.ask'), ['question' => 'What is the capital of France?'])
            ->assertOk()
            ->assertSee(RagService::NOT_FOUND);

        DocumentAssistant::assertNeverPrompted();
    }

    public function test_search_can_be_limited_to_one_document(): void
    {
        DocumentAssistant::fake()->preventStrayPrompts();

        // The warranty chunk lives in faq.md, so searching only policy.pdf finds nothing.
        $this->post(route('chat.ask'), [
            'question' => 'How long is the warranty?',
            'document_id' => $this->policy->id,
        ])->assertSee(RagService::NOT_FOUND);

        DocumentAssistant::assertNeverPrompted();
    }

    public function test_documents_that_are_still_processing_are_not_searched(): void
    {
        $this->faq->update(['status' => DocumentStatus::Processing]);

        $sources = app(RagService::class)->retrieve('How long is the warranty?');

        $this->assertTrue($sources->isEmpty());
    }

    public function test_the_answer_streams_as_server_sent_events(): void
    {
        DocumentAssistant::fake(['Refunds take 14 days [1].']);

        $response = $this->post(route('chat.stream'), ['question' => 'How long do refunds take?']);

        $response->assertOk();
        $this->assertStringContainsString('text/event-stream', $response->headers->get('Content-Type'));

        $body = $response->streamedContent();

        $this->assertStringContainsString("event: sources\ndata: [{\"number\":1,\"title\":\"policy.pdf\"", $body);
        $this->assertStringContainsString("event: delta\ndata: {\"text\":\"Refunds\"}", $body);
        $this->assertStringContainsString("event: delta\ndata: {\"text\":\" [1].\"}", $body);
        $this->assertStringEndsWith("event: done\ndata: []\n\n", $body);
    }

    public function test_a_question_is_required(): void
    {
        $this->postJson(route('chat.stream'), ['question' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('question');
    }

    /**
     * @param  list<string>  $chunks
     */
    protected function readyDocument(string $title, array $chunks): Document
    {
        $document = Document::create([
            'title' => $title,
            'path' => "documents/{$title}",
            'mime_type' => 'text/plain',
            'status' => DocumentStatus::Ready,
        ]);

        foreach ($chunks as $i => $content) {
            $document->chunks()->create([
                'chunk_index' => $i,
                'content' => $content,
                'embedding' => $this->topicVector($content),
            ]);
        }

        return $document;
    }
}
