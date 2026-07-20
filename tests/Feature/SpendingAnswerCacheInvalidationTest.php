<?php

namespace Tests\Feature;

use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Regression coverage for the spending-answer cache invalidation
 * mechanism (see Transaction::booted()):
 *
 * 1. The version must actually bump under the database cache store, this
 *    app's real configured default: relying on Cache::increment() alone
 *    does not create a missing key under that store, unlike the array
 *    store the rest of the test suite runs under by default (phpunit.xml
 *    sets CACHE_STORE=array), which would otherwise mask the gap.
 * 2. The version must bump on both creating a transaction and updating
 *    one, since IndexTransactionsCommand gives a transaction its
 *    embedding via an update, not a create, and that is exactly the
 *    change that makes it visible to AskSpendingCommand's RAG retrieval.
 */
class SpendingAnswerCacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_version_bumps_on_creation_under_the_database_cache_store(): void
    {
        config(['cache.default' => 'database']);

        $before = Cache::get(Transaction::SPENDING_ANSWERS_CACHE_VERSION_KEY, 0);

        Transaction::factory()->create();

        $after = Cache::get(Transaction::SPENDING_ANSWERS_CACHE_VERSION_KEY, 0);

        $this->assertSame($before + 1, $after);
    }

    public function test_the_version_bumps_again_when_an_existing_transaction_is_updated(): void
    {
        config(['cache.default' => 'database']);

        $transaction = Transaction::factory()->create(['embedding' => null]);

        $afterCreate = Cache::get(Transaction::SPENDING_ANSWERS_CACHE_VERSION_KEY, 0);

        // The exact change IndexTransactionsCommand makes: adding an
        // embedding to a transaction that already exists.
        $transaction->update(['embedding' => [1.0, 0.0]]);

        $afterUpdate = Cache::get(Transaction::SPENDING_ANSWERS_CACHE_VERSION_KEY, 0);

        $this->assertSame($afterCreate + 1, $afterUpdate);
    }
}
