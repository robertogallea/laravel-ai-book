<?php

namespace Tests\Feature;

use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Laravel\Ai\Embeddings;
use RuntimeException;
use Tests\TestCase;

class IndexTransactionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_computes_and_stores_an_embedding_for_every_unindexed_transaction(): void
    {
        $restaurant = Transaction::factory()->create([
            'merchant' => "Mario's Diner",
            'category' => 'restaurants',
            'occurred_at' => '2026-07-15',
        ]);

        $transportation = Transaction::factory()->create([
            'merchant' => 'City Metro',
            'category' => 'transportation',
            'occurred_at' => '2026-07-16',
        ]);

        Embeddings::fake([
            [[1.0, 0.0], [0.0, 1.0]],
        ]);

        $this->artisan('assistant:index-transactions')
            ->expectsOutputToContain('Indexed 2 transaction(s).')
            ->assertExitCode(0);

        $this->assertEquals([1.0, 0.0], $restaurant->fresh()->embedding);
        $this->assertEquals([0.0, 1.0], $transportation->fresh()->embedding);

        Embeddings::assertGenerated(
            fn ($prompt) => $prompt->inputs === [$restaurant->description(), $transportation->description()]
        );
    }

    public function test_it_leaves_an_already_indexed_transaction_untouched(): void
    {
        Transaction::factory()->create([
            'merchant' => "Mario's Diner",
            'category' => 'restaurants',
            'occurred_at' => '2026-07-15',
            'embedding' => [1.0, 0.0],
        ]);

        Embeddings::fake();

        $this->artisan('assistant:index-transactions')
            ->expectsOutputToContain('Every transaction is already indexed.')
            ->assertExitCode(0);

        Embeddings::assertNothingGenerated();
    }

    public function test_a_provider_failure_is_reported_gracefully_and_indexes_nothing(): void
    {
        $transaction = Transaction::factory()->create();

        Embeddings::fake(fn () => throw new ConnectionException('cURL error 28: Connection timed out.'));

        $this->artisan('assistant:index-transactions')
            ->expectsOutputToContain('Could not reach the embeddings provider')
            ->assertExitCode(1);

        $this->assertNull($transaction->fresh()->embedding);
    }

    public function test_it_refuses_to_index_when_the_provider_returns_a_mismatched_number_of_embeddings(): void
    {
        Transaction::factory()->count(2)->create();

        // Only one embedding for two pending transactions: positionally
        // matching them back up would misattribute at least one.
        Embeddings::fake([
            [[1.0, 0.0]],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Expected 2 embedding(s) from the provider, got 1');

        $this->artisan('assistant:index-transactions');
    }
}
