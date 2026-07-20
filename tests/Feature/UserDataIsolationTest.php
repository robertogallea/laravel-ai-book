<?php

namespace Tests\Feature;

use App\Ai\Agents\FinanceAssistant;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Embeddings;
use Tests\TestCase;

/**
 * The application now has more than one user on file, and
 * assistant:ask-spending accepts a --user option to say who is asking. What
 * it does not have yet is anything that actually restricts retrieval to
 * that specific user's own transactions: the option only confirms the
 * given email exists.
 */
class UserDataIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_users_question_can_surface_another_users_transaction(): void
    {
        $alice = User::factory()->create(['email' => 'alice@example.com']);
        $bob = User::factory()->create(['email' => 'bob@example.com']);

        // Alice has no transactions of her own on file. Bob's private
        // therapy session is the only one indexed.
        $bobsTransaction = Transaction::factory()->create([
            'user_id' => $bob->id,
            'merchant' => 'Private Therapy Clinic',
            'category' => 'other',
            'amount' => 200.00,
            'occurred_at' => '2026-07-10',
            'embedding' => [1.0, 0.0],
        ]);

        Embeddings::fake([[[1.0, 0.0]]]);

        FinanceAssistant::fake([
            'You spent $200.00 recently, at Private Therapy Clinic.',
        ]);

        $this->artisan('assistant:ask-spending', [
            'question' => 'How much have I spent recently?',
            '--user' => 'alice@example.com',
        ])->assertExitCode(0);

        // Alice asked the question, but the transaction folded into the
        // prompt belongs to Bob: the --user option identified who was
        // asking without restricting whose data could answer them.
        FinanceAssistant::assertPrompted(
            fn ($prompt) => $prompt->contains($bobsTransaction->description())
        );
    }

    public function test_the_user_option_only_validates_that_the_email_is_on_record(): void
    {
        FinanceAssistant::fake();

        $this->artisan('assistant:ask-spending', [
            'question' => 'How much have I spent recently?',
            '--user' => 'nobody@example.com',
        ])
            ->expectsOutputToContain('No user found with email "nobody@example.com"')
            ->assertExitCode(2);

        FinanceAssistant::assertNeverPrompted();
    }
}
