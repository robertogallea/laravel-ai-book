<?php

namespace Tests\Feature;

use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Embeddings;
use Tests\TestCase;

class IndexTransactionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_computes_and_stores_an_embedding_for_every_unindexed_transaction(): void
    {
        $restaurant = Transaction::create([
            'merchant' => 'Trattoria da Mario',
            'category' => 'restaurants',
            'amount' => 38.50,
            'occurred_at' => '2026-07-15',
        ]);

        $transportation = Transaction::create([
            'merchant' => 'City Metro',
            'category' => 'transportation',
            'amount' => 45.00,
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
        Transaction::create([
            'merchant' => 'Trattoria da Mario',
            'category' => 'restaurants',
            'amount' => 38.50,
            'occurred_at' => '2026-07-15',
            'embedding' => [1.0, 0.0],
        ]);

        Embeddings::fake();

        $this->artisan('assistant:index-transactions')
            ->expectsOutputToContain('Every transaction is already indexed.')
            ->assertExitCode(0);

        Embeddings::assertNothingGenerated();
    }
}
