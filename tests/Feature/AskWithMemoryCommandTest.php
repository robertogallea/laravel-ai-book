<?php

namespace Tests\Feature;

use App\Ai\Agents\FactExtractor;
use App\Ai\Agents\FinanceAssistant;
use App\Models\MemoryFact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Laravel\Ai\Embeddings;
use Tests\TestCase;

class AskWithMemoryCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_question_gets_only_the_bare_question_when_nothing_is_remembered_yet(): void
    {
        FinanceAssistant::fake([
            "I don't have any information about a savings goal for you.",
        ]);

        FactExtractor::fake([
            ['fact' => null],
        ]);

        $this->artisan('assistant:ask-with-memory', [
            'question' => 'What is my savings goal?',
        ])
            ->expectsOutputToContain("I don't have any information about a savings goal")
            ->assertExitCode(0);

        FinanceAssistant::assertPrompted(
            fn ($prompt) => $prompt->prompt === 'What is my savings goal?'
        );

        Embeddings::assertNothingGenerated();
        $this->assertSame(0, MemoryFact::count());
    }

    public function test_a_stated_goal_is_extracted_and_persisted_with_its_own_embedding(): void
    {
        FinanceAssistant::fake([
            "Got it, I'll keep that in mind.",
        ]);

        FactExtractor::fake([
            ['fact' => 'The user wants to save 200 dollars a month for vacation.'],
        ]);

        Embeddings::fake([
            [[1.0, 0.0]],
        ]);

        $this->artisan('assistant:ask-with-memory', [
            'question' => 'I want to save 200 dollars a month for vacation.',
        ])->assertExitCode(0);

        $this->assertSame(1, MemoryFact::count());

        $fact = MemoryFact::sole();
        $this->assertSame('The user wants to save 200 dollars a month for vacation.', $fact->content);
        // Round-tripped through the json cast: PHP decodes 1.0 back as
        // an int once it has no fractional part, so this compares
        // loosely rather than asserting the exact same PHP type.
        $this->assertEquals([1.0, 0.0], $fact->embedding);

        FactExtractor::assertPrompted(
            fn ($prompt) => $prompt->prompt === 'I want to save 200 dollars a month for vacation.'
        );
    }

    public function test_a_later_session_recalls_a_fact_remembered_from_an_earlier_one(): void
    {
        // Stands in for a fact a previous, separate invocation of this
        // same command already extracted and persisted, embedding already
        // computed at that time, exactly like MemoryFact::create() does
        // in AskWithMemoryCommand::rememberAnyFact().
        MemoryFact::factory()->create([
            'content' => 'The user wants to save 200 dollars a month for vacation.',
            'embedding' => [1.0, 0.0],
        ]);

        // The query embedding points in the same direction as the
        // remembered fact above, so it clears the relevance floor.
        Embeddings::fake([
            [[1.0, 0.0]],
        ]);

        FinanceAssistant::fake([
            'You are saving toward 200 dollars a month for vacation.',
        ]);

        FactExtractor::fake([
            ['fact' => null],
        ]);

        $this->artisan('assistant:ask-with-memory', [
            'question' => 'What is my savings goal?',
        ])
            ->expectsOutputToContain('You are saving toward 200 dollars a month for vacation.')
            ->assertExitCode(0);

        FinanceAssistant::assertPrompted(
            fn ($prompt) => $prompt->contains('The user wants to save 200 dollars a month for vacation.')
                && $prompt->contains('What is my savings goal?')
        );

        Embeddings::assertGenerated(
            fn ($prompt) => $prompt->inputs === ['What is my savings goal?']
        );
    }

    public function test_an_unrelated_remembered_fact_does_not_clear_the_relevance_floor(): void
    {
        MemoryFact::factory()->create([
            'content' => 'The user prefers vegetarian restaurant recommendations.',
            'embedding' => [0.0, 1.0],
        ]);

        // Orthogonal to the only remembered fact: nothing clears the
        // relevance floor, even though something has been remembered.
        Embeddings::fake([
            [[1.0, 0.0]],
        ]);

        FinanceAssistant::fake([
            "I don't have any information about a savings goal for you.",
        ]);

        FactExtractor::fake([
            ['fact' => null],
        ]);

        $this->artisan('assistant:ask-with-memory', [
            'question' => 'What is my savings goal?',
        ])->assertExitCode(0);

        FinanceAssistant::assertPrompted(
            fn ($prompt) => $prompt->prompt === 'What is my savings goal?'
        );
    }

    public function test_a_provider_failure_while_retrieving_context_is_reported_gracefully(): void
    {
        MemoryFact::factory()->create(['embedding' => [1.0, 0.0]]);

        Embeddings::fake(fn () => throw new ConnectionException('cURL error 28: Connection timed out.'));

        FinanceAssistant::fake();

        $this->artisan('assistant:ask-with-memory', [
            'question' => 'What is my savings goal?',
        ])
            ->expectsOutputToContain('Could not reach the embeddings provider')
            ->assertExitCode(1);

        FinanceAssistant::assertNeverPrompted();
    }

    public function test_a_failure_while_remembering_is_warned_about_but_does_not_fail_the_command(): void
    {
        FinanceAssistant::fake([
            "Got it, I'll keep that in mind.",
        ]);

        FactExtractor::fake(fn () => throw new ConnectionException('cURL error 28: Connection timed out.'));

        $this->artisan('assistant:ask-with-memory', [
            'question' => 'I want to save 200 dollars a month for vacation.',
        ])
            ->expectsOutputToContain("Got it, I'll keep that in mind.")
            ->expectsOutputToContain('Could not save this session to memory')
            ->assertExitCode(0);

        $this->assertSame(0, MemoryFact::count());
    }
}
