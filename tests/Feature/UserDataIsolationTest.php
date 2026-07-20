<?php

namespace Tests\Feature;

use App\Ai\Agents\FinanceAssistant;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Embeddings;
use Mockery;
use Tests\TestCase;

/**
 * Retrieval is now restricted, from the query itself onward, to
 * transactions owned by the resolved --user: the same scenario that
 * previously surfaced another user's transaction (see the removed
 * assertion this file used to carry) no longer can, because that
 * transaction is never a retrieval candidate in the first place.
 */
class UserDataIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_users_question_cannot_surface_another_users_transaction(): void
    {
        $alice = User::factory()->create(['email' => 'alice@example.com']);
        $bob = User::factory()->create(['email' => 'bob@example.com']);

        // Alice has no transactions of her own on file. Bob's private
        // therapy session is the only one indexed.
        Transaction::factory()->create([
            'user_id' => $bob->id,
            'merchant' => 'Private Therapy Clinic',
            'category' => 'other',
            'amount' => 200.00,
            'occurred_at' => '2026-07-10',
            'embedding' => [1.0, 0.0],
        ]);

        FinanceAssistant::fake([
            "I don't have your transaction history in front of me, so I can't give you an exact figure.",
        ]);

        $this->artisan('assistant:ask-spending', [
            'question' => 'How much have I spent recently?',
            '--user' => 'alice@example.com',
        ])->assertExitCode(0);

        // Nothing is indexed under Alice's own ownership, so the question
        // reaches the assistant unchanged: Bob's transaction never enters
        // consideration, not even to be discarded by the relevance floor.
        FinanceAssistant::assertPrompted(
            fn ($prompt) => $prompt->prompt === 'How much have I spent recently?'
        );

        Embeddings::assertNothingGenerated();
    }

    public function test_each_users_question_is_grounded_only_in_that_users_own_transactions(): void
    {
        $alice = User::factory()->create(['email' => 'alice@example.com']);
        $bob = User::factory()->create(['email' => 'bob@example.com']);

        $alicesGroceries = Transaction::factory()->create([
            'user_id' => $alice->id,
            'merchant' => 'Green Grocer',
            'category' => 'groceries',
            'amount' => 64.35,
            'occurred_at' => '2026-07-12',
            'embedding' => [1.0, 0.0],
        ]);

        $bobsTherapySession = Transaction::factory()->create([
            'user_id' => $bob->id,
            'merchant' => 'Private Therapy Clinic',
            'category' => 'other',
            'amount' => 200.00,
            'occurred_at' => '2026-07-10',
            'embedding' => [1.0, 0.0],
        ]);

        Embeddings::fake([[[1.0, 0.0]]]);

        FinanceAssistant::fake([
            'You spent $64.35 on groceries recently, at Green Grocer.',
        ]);

        $this->artisan('assistant:ask-spending', [
            'question' => 'How much have I spent recently?',
            '--user' => 'alice@example.com',
        ])->assertExitCode(0);

        FinanceAssistant::assertPrompted(
            fn ($prompt) => $prompt->contains($alicesGroceries->description())
                && ! $prompt->contains($bobsTherapySession->description())
        );
    }

    // Unknown-email rejection is covered once, in AskSpendingCommandTest,
    // not duplicated here: this file's own concern is isolation between
    // known users, not option validation.

    public function test_the_trace_of_a_grounded_question_records_which_user_asked_it(): void
    {
        $alice = User::factory()->create(['email' => 'alice@example.com']);

        Log::spy();

        FinanceAssistant::fake([
            "I don't have your transaction history in front of me, so I can't give you an exact figure.",
        ]);

        $this->artisan('assistant:ask-spending', [
            'question' => 'How much have I spent recently?',
            '--user' => 'alice@example.com',
        ])->assertExitCode(0);

        Log::shouldHaveReceived('info')
            ->once()
            ->with('llm_call', Mockery::on(fn (array $trace) => $trace['user_id'] === $alice->id));
    }
}
