<?php

namespace Tests\Feature;

use App\Ai\Agents\FinanceAssistant;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Laravel\Ai\Embeddings;
use Tests\TestCase;

class AskSpendingCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_question_gets_only_a_generic_answer_when_no_transaction_is_indexed_yet(): void
    {
        FinanceAssistant::fake([
            "I don't have your transaction history in front of me, so I can't give you an exact figure. "
            .'As a general guideline, keeping restaurant spending under 10-15% of your monthly budget works well for most people.',
        ]);

        $this->artisan('assistant:ask-spending', [
            'question' => 'How much did I spend on restaurants this month?',
        ])
            ->expectsOutputToContain("I don't have your transaction history")
            ->assertExitCode(0);

        FinanceAssistant::assertPrompted(
            fn ($prompt) => $prompt->prompt === 'How much did I spend on restaurants this month?'
        );

        Embeddings::assertNothingGenerated();
    }

    public function test_a_question_is_grounded_in_the_most_relevant_indexed_transactions(): void
    {
        $restaurant = Transaction::factory()->create([
            'merchant' => "Mario's Diner",
            'category' => 'restaurants',
            'amount' => 38.50,
            'occurred_at' => '2026-07-15',
            'embedding' => [1.0, 0.0],
        ]);

        $transportation = Transaction::factory()->create([
            'merchant' => 'City Metro',
            'category' => 'transportation',
            'amount' => 45.00,
            'occurred_at' => '2026-07-16',
            'embedding' => [0.0, 1.0],
        ]);

        // The query embedding points in the same direction as the restaurant
        // transaction, and orthogonally (zero similarity) to the
        // transportation one, which sits below the relevance floor.
        Embeddings::fake([[[1.0, 0.0]]]);

        FinanceAssistant::fake([
            "You spent $38.50 on restaurants this month, at Mario's Diner.",
        ]);

        $this->artisan('assistant:ask-spending', [
            'question' => 'How much did I spend on restaurants this month?',
        ])
            ->expectsOutputToContain('You spent $38.50 on restaurants this month')
            ->assertExitCode(0);

        FinanceAssistant::assertPrompted(
            fn ($prompt) => $prompt->contains($restaurant->description())
                && ! $prompt->contains($transportation->description())
                && $prompt->contains('How much did I spend on restaurants this month?')
        );

        Embeddings::assertGenerated(
            fn ($prompt) => $prompt->inputs === ['How much did I spend on restaurants this month?']
        );
    }

    public function test_a_question_falls_back_to_the_bare_question_when_nothing_indexed_clears_the_relevance_floor(): void
    {
        Transaction::factory()->create([
            'merchant' => 'City Metro',
            'category' => 'transportation',
            'occurred_at' => '2026-07-16',
            'embedding' => [0.0, 1.0],
        ]);

        // Orthogonal to every indexed transaction: nothing clears the
        // relevance floor, even though something is indexed.
        Embeddings::fake([[[1.0, 0.0]]]);

        FinanceAssistant::fake([
            "I don't have anything relevant to your question about restaurant spending.",
        ]);

        $this->artisan('assistant:ask-spending', [
            'question' => 'How much did I spend on restaurants this month?',
        ])->assertExitCode(0);

        FinanceAssistant::assertPrompted(
            fn ($prompt) => $prompt->prompt === 'How much did I spend on restaurants this month?'
        );
    }

    public function test_a_provider_failure_while_retrieving_context_is_reported_gracefully(): void
    {
        Transaction::factory()->create([
            'embedding' => [1.0, 0.0],
        ]);

        Embeddings::fake(fn () => throw new ConnectionException('cURL error 28: Connection timed out.'));

        FinanceAssistant::fake();

        $this->artisan('assistant:ask-spending', [
            'question' => 'How much did I spend on restaurants this month?',
        ])
            ->expectsOutputToContain('Could not reach the embeddings provider')
            ->assertExitCode(1);

        FinanceAssistant::assertNeverPrompted();
    }
}
